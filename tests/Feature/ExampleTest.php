<?php

test('the home page renders successfully', function () {
    $response = $this->get('/');

    $response->assertOk();
});
