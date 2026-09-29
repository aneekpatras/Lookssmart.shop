<?php

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceImport;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * QUEUE_CONNECTION=sync in phpunit.xml — ProcessServiceImportJob::dispatch() runs inline during
 * these tests, exactly the way a real queue worker would process it, without needing Queue::fake()
 * (which would only assert dispatch happened, not that the job produces correct results).
 */
$makeConfirmedUser = function (string $role): User {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    return $user;
};

function makeImportXlsx(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $headings = ['sku', 'name', 'category', 'duration_min', 'buffer_min', 'base_price', 'is_active', 'is_featured', 'description'];
    $sheet->fromArray($headings, null, 'A1');
    $sheet->fromArray($rows, null, 'A2');

    $path = sys_get_temp_dir() . '/' . uniqid('import_test_', true) . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'services.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('requires catalog.manage to upload an import file', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $staff = $makeConfirmedUser('staff');
    $file = makeImportXlsx([['CUT-1', 'Test Cut', 'Hair', 30, 5, 40, 1, 0, 'desc']]);

    $response = $this->actingAs($staff)->post('/admin/services-import', ['file' => $file]);

    $response->assertForbidden();
    expect(ServiceImport::count())->toBe(0);
});

it('processes an upload into a previewed import with create/update/error rows', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = ServiceCategory::factory()->create(['name' => 'Hair']);
    $existing = Service::factory()->create([
        'service_category_id' => $category->id,
        'sku' => 'CUT-EXIST',
        'name' => 'Old Name',
        'base_price' => 30,
    ]);

    $file = makeImportXlsx([
        ['CUT-NEW', 'Brand New Cut', 'Hair', 30, 5, 40, 1, 0, 'A fresh cut'],
        ['CUT-EXIST', 'Updated Name', 'Hair', 45, 10, 55, 1, 1, 'Updated description'],
        ['', 'Bad Row No Category', 'Does Not Exist', 30, 0, 20, 1, 0, ''],
    ]);

    $response = $this->actingAs($admin)->post('/admin/services-import', ['file' => $file]);
    $response->assertRedirect();

    $import = ServiceImport::first();
    expect($import)->not->toBeNull()
        ->and($import->status)->toBe('previewed')
        ->and($import->create_count)->toBe(1)
        ->and($import->update_count)->toBe(1)
        ->and($import->error_count)->toBe(1);

    $rows = $import->rows()->orderBy('row_number')->get();
    expect($rows[0]->action)->toBe('create')
        ->and($rows[1]->action)->toBe('update')
        ->and($rows[1]->matched_service_id)->toBe($existing->id)
        ->and($rows[2]->action)->toBe('error');
});

it('sanitizes a formula-injection payload in a cell before it is ever persisted', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    ServiceCategory::factory()->create(['name' => 'Hair']);

    $file = makeImportXlsx([
        ['=cmd|/c calc', '=SUM(A1:A9)', 'Hair', 30, 0, 40, 1, 0, ''],
    ]);

    $this->actingAs($admin)->post('/admin/services-import', ['file' => $file]);

    $import = ServiceImport::first();
    $row = $import->rows()->first();

    expect($row->data['sku'])->toStartWith("'=")
        ->and($row->data['name'])->toStartWith("'=");
});

it('commits an import, creating and updating real services in a transaction', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = ServiceCategory::factory()->create(['name' => 'Hair']);
    $existing = Service::factory()->create([
        'service_category_id' => $category->id,
        'sku' => 'CUT-EXIST',
        'name' => 'Old Name',
        'base_price' => 30,
    ]);

    $file = makeImportXlsx([
        ['CUT-NEW', 'Brand New Cut', 'Hair', 30, 5, 40, 1, 0, 'A fresh cut'],
        ['CUT-EXIST', 'Updated Name', 'Hair', 45, 10, 55, 1, 1, 'Updated description'],
    ]);

    $this->actingAs($admin)->post('/admin/services-import', ['file' => $file]);
    $import = ServiceImport::first();

    $response = $this->actingAs($admin)->post("/admin/services-import/{$import->id}/commit");
    $response->assertRedirect();

    expect($import->fresh()->status)->toBe('committed')
        ->and(Service::where('sku', 'CUT-NEW')->exists())->toBeTrue()
        ->and($existing->fresh()->name)->toBe('Updated Name')
        ->and((float) $existing->fresh()->base_price)->toBe(55.0);

    $activity = Activity::where('description', 'service import committed')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id);
});

it('refuses to commit an import that is not in previewed status', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $import = ServiceImport::create([
        'original_filename' => 'x.xlsx',
        'file_path' => 'service-imports/x.xlsx',
        'status' => 'committed',
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post("/admin/services-import/{$import->id}/commit");

    $response->assertSessionHasErrors('import');
    expect($import->fresh()->status)->toBe('committed');
});

it('rejects a file over the max row cap (default 2000)', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    ServiceCategory::factory()->create(['name' => 'Hair']);

    $rows = array_fill(0, 2001, ['SKU', 'Name', 'Hair', 30, 0, 40, 1, 0, '']);
    $file = makeImportXlsx($rows);

    $this->actingAs($admin)->post('/admin/services-import', ['file' => $file]);

    $import = ServiceImport::first();
    expect($import->fresh()->status)->toBe('failed')
        ->and($import->fresh()->failure_reason)->toContain('maximum');
});

it('rejects a non-xlsx file upload', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $file = UploadedFile::fake()->create('not-a-spreadsheet.txt', 10, 'text/plain');

    $response = $this->actingAs($admin)->post('/admin/services-import', ['file' => $file]);

    $response->assertSessionHasErrors('file');
});

it('imports the optional image_url column into stock_image_url on commit, without wiping it when a re-import leaves the cell blank', function () use ($makeConfirmedUser) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = $makeConfirmedUser('admin');
    $category = ServiceCategory::factory()->create(['name' => 'Hair']);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['sku', 'name', 'category', 'duration_min', 'buffer_min', 'base_price', 'is_active', 'is_featured', 'description', 'image_url'], null, 'A1');
    $sheet->fromArray([['CUT-NEW', 'Brand New Cut', 'Hair', 30, 5, 40, 1, 0, 'A fresh cut', 'https://images.example.com/cut.jpg']], null, 'A2');
    $path = sys_get_temp_dir() . '/' . uniqid('import_test_', true) . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $file = new UploadedFile($path, 'services.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $this->actingAs($admin)->post('/admin/services-import', ['file' => $file]);
    $import = ServiceImport::first();
    $this->actingAs($admin)->post("/admin/services-import/{$import->id}/commit");

    $service = Service::where('sku', 'CUT-NEW')->firstOrFail();
    expect($service->stock_image_url)->toBe('https://images.example.com/cut.jpg');

    // Re-importing the same SKU with the image_url cell left blank (e.g. a file exported before
    // this column existed) must UPDATE the row without wiping the image already set above.
    $reimportFile = makeImportXlsx([
        ['CUT-NEW', 'Brand New Cut', 'Hair', 30, 5, 40, 1, 0, 'A fresh cut'],
    ]);
    $this->actingAs($admin)->post('/admin/services-import', ['file' => $reimportFile]);
    $reimport = ServiceImport::latest('id')->first();
    $this->actingAs($admin)->post("/admin/services-import/{$reimport->id}/commit");

    expect($service->fresh()->stock_image_url)->toBe('https://images.example.com/cut.jpg');
});
