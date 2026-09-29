<?php

namespace App\Support;

use App\Models\Service;

/**
 * Supplies the treatment procedure, benefits/suitability and aftercare shown on
 * `/services/{slug}`.
 *
 * WHY THIS IS KEYED BY TREATMENT FAMILY RATHER THAN PER SERVICE: the catalog has 55 services, and
 * authoring 55 individually-written clinical protocols would mean inventing 55 sets of specifics
 * the salon never actually supplied — fabricated detail dressed up as real guidance, which is worse
 * than none on a page a client might follow after a chemical treatment. Instead each service is
 * resolved to a treatment FAMILY (chemical smoothing, colour, extensions, styling, facial, makeup,
 * lashes, hair removal, spa) whose guidance is genuinely shared and genuinely accurate for every
 * member of it — a rebonding and an X-Tenso really do have the same 72-hour no-wash rule, and every
 * facial really does need sun protection afterwards.
 *
 * The family is derived from the SKU prefix, which `SalonCatalogSeeder` assigns deliberately for
 * exactly this purpose. An unrecognised SKU falls back to its category, and then to a generic
 * consultation-first entry — never to an empty page, and never to guidance borrowed from an
 * unrelated family (advising sun protection after a haircut would read as obviously wrong).
 *
 * This is editorial content, not medical advice, and it is deliberately kept out of the database:
 * it is versioned copy that belongs with the code, and the salon can revise it in one place.
 */
class ServiceGuide
{
    /**
     * Ordered, longest-prefix-first so `HAIR-EXT-` is matched before `HAIR-`.
     *
     * @var array<string, string>
     */
    private const SKU_FAMILIES = [
        'HAIR-XTENSO' => 'smoothing',
        'HAIR-EXTENSO' => 'smoothing',
        'HAIR-REBOND' => 'smoothing',
        'HAIR-KERATIN' => 'smoothing',
        'HAIR-BOTOX' => 'smoothing',
        'HAIR-PERM' => 'smoothing',
        'HAIR-COLOR' => 'colour',
        'HAIR-STREAKS' => 'colour',
        'HAIR-BALAYAGE' => 'colour',
        'HAIR-BABYLIGHTS' => 'colour',
        'HAIR-EXT-' => 'extensions',
        'HAIR-SCALP' => 'scalp',
        'HAIR-HOTOIL' => 'scalp',
        'HAIR-CUT' => 'styling',
        'HAIR-STYLE' => 'styling',
        'HAIR-BLOWDRY' => 'styling',
        'HAIR-IRON' => 'styling',
        'SKIN-' => 'facial',
        'MUA-' => 'makeup',
        'LASH-' => 'lashes',
        'WAX-' => 'waxing',
        'THR-' => 'threading',
        'SPA-' => 'spa',
        'SPEC-NAIL' => 'nails',
        'SPEC-MEHNDI' => 'mehndi',
        'SPEC-BRIDAL' => 'consultation',
    ];

    /**
     * @return array{procedure: list<array{title: string, detail: string}>, benefits: list<string>, suitability: string, aftercare: list<string>}
     */
    public static function for(Service $service): array
    {
        $family = self::family($service);

        return self::guides()[$family] ?? self::guides()['consultation'];
    }

    private static function family(Service $service): string
    {
        $sku = (string) $service->sku;

        foreach (self::SKU_FAMILIES as $prefix => $family) {
            if (str_starts_with($sku, $prefix)) {
                return $family;
            }
        }

        // No SKU match (a service added through the admin UI rather than the seeder) — fall back to
        // the broad category, then to the generic consultation guidance.
        return match ($service->category?->slug) {
            'hair-styling-treatments' => 'styling',
            'skin-facial-care' => 'facial',
            'bridal-party-makeup' => 'makeup',
            'lashes-waxing-threading' => 'waxing',
            'massage-spa-specials' => 'spa',
            default => 'consultation',
        };
    }

    /**
     * @return array<string, array{procedure: list<array{title: string, detail: string}>, benefits: list<string>, suitability: string, aftercare: list<string>}>
     */
    private static function guides(): array
    {
        return [
            'smoothing' => [
                'procedure' => [
                    ['title' => 'Consultation & strand test', 'detail' => 'We assess your hair\'s texture, porosity and any previous chemical work, then test a hidden strand so we know exactly how it will respond before committing.'],
                    ['title' => 'Clarifying wash', 'detail' => 'A deep clarifying cleanse removes product build-up so the formula can reach the hair shaft evenly.'],
                    ['title' => 'Formula application', 'detail' => 'The smoothing cream is applied section by section, kept off the scalp, and timed precisely to your hair type.'],
                    ['title' => 'Processing & neutralising', 'detail' => 'Once the bonds have relaxed, a neutraliser is applied to reset them in their new, straighter position.'],
                    ['title' => 'Seal & finish', 'detail' => 'We seal the cuticle with a flat-iron press and finish with a protein or keratin layer for shine.'],
                ],
                'benefits' => [
                    'Dramatically reduced frizz, including in humidity',
                    'Far less daily heat styling needed',
                    'Smooth, high-shine finish that lasts months',
                    'Easier detangling and faster drying time',
                ],
                'suitability' => 'Best for thick, coarse, frizzy or unruly hair. If your hair is heavily bleached, badly damaged or recently chemically treated, we may recommend a repair course first — the strand test tells us honestly, and we will say so rather than proceed.',
                'aftercare' => [
                    'Do not wash, tie, clip or tuck your hair behind your ears for 72 hours — a bend set in now can become permanent.',
                    'Use a sulphate-free and salt-free shampoo from the first wash onwards; sulphates strip the treatment out early.',
                    'Avoid swimming pools and seawater for at least two weeks — chlorine and salt both shorten the result significantly.',
                    'Apply a leave-in serum or light oil to the ends daily to keep the cuticle sealed.',
                    'Book a nourishing mask or hair spa treatment every 4-6 weeks to extend the smoothness.',
                    'Delay any colour service for at least two weeks after the treatment.',
                ],
            ],

            'colour' => [
                'procedure' => [
                    ['title' => 'Shade consultation', 'detail' => 'We match a target shade against your skin tone, natural base and any existing colour, and agree how much lift is realistically achievable in one sitting.'],
                    ['title' => 'Patch test', 'detail' => 'A skin patch test is carried out where required, especially on a first visit or after any previous reaction.'],
                    ['title' => 'Sectioning & application', 'detail' => 'Colour is applied to clean, dry sections — foils for highlights, freehand sweeps for balayage, root-to-tip for a full colour.'],
                    ['title' => 'Development & toning', 'detail' => 'Colour develops under supervision, then a toner or gloss cancels unwanted warmth and evens the final result.'],
                    ['title' => 'Bond care & finish', 'detail' => 'A bond-rebuilding treatment goes on before a blow-dry so you leave seeing the finished colour in full.'],
                ],
                'benefits' => [
                    'Colour matched deliberately to your skin tone, not a swatch',
                    'Visible depth and dimension rather than a flat block of colour',
                    'Toned to remove brassiness before you leave',
                    'Grey coverage where you want it',
                ],
                'suitability' => 'Suitable for most hair. Previously box-dyed, henna-treated or heavily bleached hair needs an honest conversation first — some target shades take more than one session to reach safely, and we would rather stage it than compromise your hair.',
                'aftercare' => [
                    'Wait 48-72 hours before your first wash so the colour molecules fully settle.',
                    'Switch to a colour-safe, sulphate-free shampoo and wash in lukewarm — not hot — water.',
                    'Use a blue or purple toning shampoo weekly if you have gone blonde or have highlights.',
                    'Always apply heat protection before styling; heat fades colour faster than washing does.',
                    'Protect against strong sun and chlorine, both of which oxidise and dull colour quickly.',
                    'Plan a root touch-up around every 4-6 weeks, or a gloss refresh every 8 weeks for balayage.',
                ],
            ],

            'extensions' => [
                'procedure' => [
                    ['title' => 'Colour & texture match', 'detail' => 'We match hair type, shade and wave pattern against your own so the join is invisible in daylight.'],
                    ['title' => 'Sectioning & placement plan', 'detail' => 'We map where weight should sit so the result adds volume without revealing attachment points.'],
                    ['title' => 'Fitting', 'detail' => 'Each strand, tape or clip row is fitted with even tension — never tight enough to pull at the root.'],
                    ['title' => 'Blend cut', 'detail' => 'The extensions are cut into your own hair, which is what makes the length look like it grew there.'],
                    ['title' => 'Styling & handover', 'detail' => 'We finish with a style and show you exactly how to brush, wash and sleep in them.'],
                ],
                'benefits' => [
                    'Immediate length and fullness',
                    'Colour-matched and blend-cut for an invisible join',
                    'Adds volume to fine or thinning hair',
                    'Temporary options can be removed the same day',
                ],
                'suitability' => 'Best when you have enough of your own hair at the attachment points to conceal them. Not advisable during active hair loss or on a very fragile scalp — we will assess and tell you plainly.',
                'aftercare' => [
                    'Brush daily with a soft loop brush, holding the root to take tension off the bonds.',
                    'Never sleep on wet extensions; dry them fully and plait loosely before bed.',
                    'Keep conditioner and oil to the mid-lengths and ends, away from the attachment points.',
                    'Keep heat tools away from bonds and tapes.',
                    'Book a maintenance and reposition appointment every 6-8 weeks as your own hair grows.',
                    'Come to us for removal — pulling them out yourself is the main cause of real damage.',
                ],
            ],

            'styling' => [
                'procedure' => [
                    ['title' => 'Consultation', 'detail' => 'We talk through the shape or finish you want, look at how your hair actually falls, and agree what will suit your routine at home.'],
                    ['title' => 'Cleanse & prep', 'detail' => 'A wash suited to your hair type, followed by a heat protectant and the right prep product for the finish.'],
                    ['title' => 'Cut or set', 'detail' => 'Precision cutting where relevant, or sectioned thermal setting for a styled finish.'],
                    ['title' => 'Finish', 'detail' => 'Brushed out, dressed and lightly fixed so it holds without feeling stiff or coated.'],
                ],
                'benefits' => [
                    'A shape that works with your hair, not against it',
                    'Immediate polished finish',
                    'Heat-protected throughout',
                    'Home styling guidance you can actually repeat',
                ],
                'suitability' => 'Suitable for all hair types and ages, including children. Tell us about any scalp sensitivity beforehand so we can adjust products and heat.',
                'aftercare' => [
                    'Sleep on a silk or satin pillowcase to keep a set style intact for longer.',
                    'Use dry shampoo at the roots to stretch the style an extra day rather than rewashing.',
                    'Always apply heat protection before re-styling at home.',
                    'Book a cut every 6-8 weeks to keep the shape and stop the ends splitting.',
                ],
            ],

            'scalp' => [
                'procedure' => [
                    ['title' => 'Scalp assessment', 'detail' => 'We look at the scalp condition — dryness, oiliness, flaking or congestion — and choose the treatment accordingly.'],
                    ['title' => 'Treatment application', 'detail' => 'Warmed oil or a targeted scalp formula is applied and worked through in sections.'],
                    ['title' => 'Massage & stimulation', 'detail' => 'Manual massage, or high-frequency stimulation where booked, encourages circulation at the follicle.'],
                    ['title' => 'Cleanse & dry', 'detail' => 'A gentle cleanse removes residue, followed by a light blow-dry.'],
                ],
                'benefits' => [
                    'Relief from tightness and tension',
                    'Improved circulation at the follicle',
                    'A calmer, less congested scalp',
                    'Genuinely relaxing — most clients find it the most restful thing we do',
                ],
                'suitability' => 'Good for dry, tight, flaky or stressed scalps. Not suitable over open sores, active infection or fresh scalp wounds — tell us and we will reschedule.',
                'aftercare' => [
                    'Leave any remaining oil in for a few hours before rinsing if your scalp is very dry.',
                    'Use a mild, non-stripping shampoo for the next few washes.',
                    'Drink plenty of water — scalp condition responds to hydration more than people expect.',
                    'Repeat every 2-4 weeks for a cumulative effect; one session is pleasant, a course is what changes things.',
                ],
            ],

            'facial' => [
                'procedure' => [
                    ['title' => 'Skin analysis', 'detail' => 'We examine your skin under a lamp, ask about sensitivities and current products, and confirm the protocol suits you today.'],
                    ['title' => 'Double cleanse', 'detail' => 'Makeup and sunscreen are removed first, then a second cleanse prepares the skin properly.'],
                    ['title' => 'Exfoliate & extract', 'detail' => 'Gentle exfoliation, followed by careful extractions only where they are genuinely needed.'],
                    ['title' => 'Active treatment', 'detail' => 'The serum or ampoule for your booked facial is applied, usually with massage and sometimes with steam or a device.'],
                    ['title' => 'Mask & protect', 'detail' => 'A targeted mask, then moisturiser and sun protection before you leave.'],
                ],
                'benefits' => [
                    'Deeply cleansed skin without that stripped, tight feeling',
                    'Brighter, more even tone',
                    'Better hydration and a smoother makeup base',
                    'A protocol chosen for your skin on the day, not a fixed menu item',
                ],
                'suitability' => 'Suitable for most skin types — tell us about pregnancy, active acne medication (particularly isotretinoin), recent peels or laser work, as these genuinely change what is safe. If a treatment is not right for you today, we will say so and suggest an alternative.',
                'aftercare' => [
                    'Wear broad-spectrum SPF 30 or higher every day, and reapply — freshly exfoliated skin burns far more easily.',
                    'Leave makeup off for at least 12 hours so the skin can settle.',
                    'Skip retinol, acids and any scrub for 3-5 days.',
                    'Avoid gyms, saunas, steam rooms and swimming pools for 24-48 hours.',
                    'Do not pick at any purging or small breakouts that surface afterwards.',
                    'For visible, lasting change, book a course every 3-4 weeks rather than relying on one visit.',
                ],
            ],

            'makeup' => [
                'procedure' => [
                    ['title' => 'Look consultation', 'detail' => 'We discuss the event, your outfit and jewellery, the lighting you will be in, and how bold you actually want to go.'],
                    ['title' => 'Skin prep', 'detail' => 'Cleansing, hydration and the right primer for your skin type — the step that decides whether the makeup lasts.'],
                    ['title' => 'Base & shade match', 'detail' => 'Foundation and concealer matched in natural light, then colour-corrected and set for your skin type.'],
                    ['title' => 'Eyes, brows & lashes', 'detail' => 'Eye design built to suit your eye shape, brows shaped and filled, and lashes applied.'],
                    ['title' => 'Contour, lips & set', 'detail' => 'Sculpting, blush and lips, finished with a setting spray chosen for the length of your event.'],
                ],
                'benefits' => [
                    'A look designed around your features and your event',
                    'Photograph-tested: no white cast under flash',
                    'Built to last through a full day or evening',
                    'Professional-grade, hygienically maintained products',
                ],
                'suitability' => 'Suitable for all skin tones and ages. Tell us in advance about sensitive eyes, contact lenses, lash glue reactions or any product allergy. For bridal bookings we strongly recommend a consultation or trial first — see our Bridal Hair & Makeup Consultation.',
                'aftercare' => [
                    'Carry a pressed powder and your lip colour for touch-ups; leave the rest alone.',
                    'Blot, never rub — rubbing lifts the base and needs a full repair.',
                    'Remove everything properly the same night with a balm or oil cleanser, then a gentle wash.',
                    'Remove strip lashes by loosening the glue with a little oil rather than pulling.',
                    'Book bridal makeup and any trial well ahead — peak wedding dates fill months in advance.',
                ],
            ],

            'lashes' => [
                'procedure' => [
                    ['title' => 'Mapping & consultation', 'detail' => 'We map length and curl to your eye shape and check the health of your natural lashes.'],
                    ['title' => 'Cleanse & isolate', 'detail' => 'Lashes are cleansed of all oil and residue, and the lower lashes are protected with a pad.'],
                    ['title' => 'Application', 'detail' => 'For extensions, each lash is isolated and a single extension attached; for a lift, your own lashes are set on a silicone shield.'],
                    ['title' => 'Setting & cure', 'detail' => 'The adhesive or lifting lotion cures fully before your eyes are opened, then we comb through and check symmetry.'],
                ],
                'benefits' => [
                    'Open, lifted eyes with no daily mascara',
                    'Mapped to your eye shape rather than a single fixed style',
                    'Extensions last several weeks with infills',
                    'A lift uses only your own lashes, with nothing to remove later',
                ],
                'suitability' => 'Not suitable during an eye infection, conjunctivitis, or with a known cyanoacrylate adhesive allergy — tell us and we will patch test first. Not recommended immediately after eye surgery.',
                'aftercare' => [
                    'Keep lashes completely dry for the first 24-48 hours while the adhesive cures.',
                    'Avoid steam, saunas and very hot showers directly on the face for 48 hours.',
                    'Do not use oil-based cleansers, removers or waterproof mascara — oil dissolves the bond.',
                    'Brush gently with the spoolie provided each morning.',
                    'Never pick or pull at extensions; that removes your own lash with them.',
                    'Book infills every 2-3 weeks to keep extensions looking full as your lashes shed naturally.',
                ],
            ],

            'waxing' => [
                'procedure' => [
                    ['title' => 'Skin check & prep', 'detail' => 'We check the skin for irritation, cuts or sunburn, then cleanse and apply a pre-wax barrier oil or powder.'],
                    ['title' => 'Wax application', 'detail' => 'Warm wax is tested for temperature and applied in the direction of growth, in manageable sections.'],
                    ['title' => 'Removal', 'detail' => 'Removed against the growth with the skin held taut, which is what keeps it quick and minimises discomfort.'],
                    ['title' => 'Soothe & finish', 'detail' => 'A post-wax soothing lotion, and a cooling mask where your service includes one, calms the area before you leave.'],
                ],
                'benefits' => [
                    'Smooth skin for 3-4 weeks, far longer than shaving',
                    'Regrowth comes back softer and finer over time',
                    'Removes dead surface skin along with the hair',
                    'Single-use spatulas and strips throughout — wax is never double-dipped',
                ],
                'suitability' => 'Hair needs to be around 5mm long for wax to grip. Not suitable on sunburnt or broken skin, or while using retinoids, isotretinoin or acid exfoliants on the area — these make skin lift. Tell us about any of these and we will advise an alternative.',
                'aftercare' => [
                    'Expect some redness for a few hours; a cool compress settles it.',
                    'Keep the area clean and dry for 24 hours — no gym, sauna, pool or hot bath.',
                    'Avoid sun exposure and self-tan on the area for 48 hours.',
                    'Wear loose clothing over freshly waxed skin to prevent friction bumps.',
                    'Start gentle exfoliation after 48 hours, 2-3 times weekly, to prevent ingrown hairs.',
                    'Rebook every 3-4 weeks — waxing at a consistent cycle genuinely thins regrowth.',
                ],
            ],

            'threading' => [
                'procedure' => [
                    ['title' => 'Shape mapping', 'detail' => 'For brows we measure against your own bone structure and facial proportions and agree the shape before starting.'],
                    ['title' => 'Prep', 'detail' => 'The area is cleansed and lightly powdered so the thread can grip cleanly.'],
                    ['title' => 'Threading', 'detail' => 'A twisted cotton thread lifts each hair from the follicle in a precise line — no chemicals and nothing applied to the skin.'],
                    ['title' => 'Soothe', 'detail' => 'A soothing gel or aloe calms the area, and we check the shape with you in the mirror.'],
                ],
                'benefits' => [
                    'Far more precise than waxing for brow shaping',
                    'No wax, no chemicals touching the skin',
                    'Safe for sensitive skin and for anyone using retinoids',
                    'Removes even very fine hair cleanly',
                ],
                'suitability' => 'Suitable for practically everyone, including sensitive skin and those on retinoids who cannot wax. Not over active acne lesions or broken skin in the immediate area.',
                'aftercare' => [
                    'Expect mild redness for an hour or so; aloe or a cool compress helps.',
                    'Leave makeup off the threaded area for the rest of the day.',
                    'Skip sauna, steam and heavy sweating for 24 hours.',
                    'Do not tweeze between appointments if you want the shape to hold.',
                    'Rebook every 2-3 weeks to keep a defined brow line.',
                ],
            ],

            'spa' => [
                'procedure' => [
                    ['title' => 'Consultation', 'detail' => 'We ask about tension areas, injuries, pregnancy and pressure preference, and adjust the treatment plan accordingly.'],
                    ['title' => 'Warm-up', 'detail' => 'The room is warmed and the treatment begins with broad, slow strokes to settle the nervous system.'],
                    ['title' => 'Main treatment', 'detail' => 'The technique for your booked service — deep pressure, assisted stretching, a full-body scrub, or heated stones along the muscle.'],
                    ['title' => 'Targeted work', 'detail' => 'Focused attention on the areas you flagged, at a pressure we keep checking with you.'],
                    ['title' => 'Rest & rehydrate', 'detail' => 'You are left to rest briefly, then offered water before you get up.'],
                ],
                'benefits' => [
                    'Real release of muscular tension, not just relaxation',
                    'Improved circulation and mobility',
                    'Better sleep for several nights afterwards',
                    'Softer, smoother skin from any scrub element',
                ],
                'suitability' => 'Tell us about pregnancy, high or low blood pressure, recent surgery, blood-thinning medication, varicose veins or any injury — some techniques need modifying and hot stones in particular are not suitable for everyone. Hot stone therapy is unsuitable during pregnancy or with impaired skin sensation.',
                'aftercare' => [
                    'Drink plenty of water for the rest of the day.',
                    'Some tenderness for 24-48 hours after deep work is normal and settles on its own.',
                    'Avoid a heavy gym session the same day; a walk or stretching is better.',
                    'Keep warm and rest if you can — the benefit continues for hours afterwards.',
                    'Moisturise well after any scrub treatment.',
                ],
            ],

            'nails' => [
                'procedure' => [
                    ['title' => 'Consultation & shape', 'detail' => 'We agree length, shape and design, and check the condition of your natural nail and cuticle.'],
                    ['title' => 'Prep', 'detail' => 'Nails are cleansed, shaped and buffed, and cuticles gently pushed back rather than cut aggressively.'],
                    ['title' => 'Extension build', 'detail' => 'Gel is sculpted to the agreed length and shape, then cured in layers.'],
                    ['title' => 'Art & finish', 'detail' => 'Your chosen design is applied and sealed with a top coat, then cuticle oil.'],
                ],
                'benefits' => [
                    'Custom length, shape and design',
                    'Hard-wearing finish that resists chipping',
                    'Instantly tidies weak or bitten nails',
                    'Lasts 3-4 weeks with proper care',
                ],
                'suitability' => 'Not suitable over a nail infection, fungal nail or badly damaged nail bed — we will look first and tell you honestly if the nail needs to recover instead.',
                'aftercare' => [
                    'Wear gloves for washing up and cleaning; prolonged water and detergent lift the gel edge.',
                    'Apply cuticle oil daily to keep the surrounding skin healthy.',
                    'Never use your nails as tools to pick, scrape or open things.',
                    'Book infills every 2-3 weeks as the nail grows out.',
                    'Come in for removal rather than peeling gel off — peeling takes layers of your natural nail with it.',
                ],
            ],

            'mehndi' => [
                'procedure' => [
                    ['title' => 'Design discussion', 'detail' => 'We look at motifs and density together — traditional, contemporary or minimal — and agree the coverage.'],
                    ['title' => 'Skin prep', 'detail' => 'The area is cleansed of oil and lotion so the paste can stain evenly.'],
                    ['title' => 'Application', 'detail' => 'The design is drawn freehand with a fine cone, worked outward so nothing is smudged.'],
                    ['title' => 'Setting', 'detail' => 'The paste is left to dry, and we explain how long to keep it on for the deepest possible stain.'],
                ],
                'benefits' => [
                    'Hand-drawn, individual design',
                    'Colour deepens over the first 48 hours',
                    'Natural paste with nothing synthetic added',
                    'Lasts one to two weeks',
                ],
                'suitability' => 'Suitable for most people. We use natural henna only — never so-called "black henna", which contains PPD and can cause severe reactions. Tell us about any previous henna reaction.',
                'aftercare' => [
                    'Leave the paste on as long as you comfortably can — 4-6 hours minimum, overnight is best.',
                    'Scrape the dried paste off rather than washing it off.',
                    'Keep the area away from water for the first 12 hours after removal.',
                    'Rub a little coconut or olive oil over the design before showering to protect it.',
                    'The stain starts orange and darkens over 48 hours — that is normal, not a fault.',
                    'Avoid chlorine, scrubbing and exfoliation over the design to keep it longer.',
                ],
            ],

            'consultation' => [
                'procedure' => [
                    ['title' => 'Sit down together', 'detail' => 'An unhurried conversation about the event, the look you have in mind, and any images you have saved.'],
                    ['title' => 'Assessment', 'detail' => 'We look at your hair and skin in person, which is the only reliable way to say what will actually work on the day.'],
                    ['title' => 'Plan & shades', 'detail' => 'We agree the approach, match shades, and note everything down against your booking.'],
                    ['title' => 'Timeline', 'detail' => 'We work backwards from your event to schedule any treatments that need lead time, and book the day itself.'],
                ],
                'benefits' => [
                    'No surprises on the day itself',
                    'A written plan kept on your booking record',
                    'Treatments that need lead time booked in the right order',
                    'Time to change your mind before it matters',
                ],
                'suitability' => 'Recommended for every bride, and for anyone planning a significant event. Bring photographs, your outfit colours and your jewellery if you have them.',
                'aftercare' => [
                    'Keep the agreed plan and shade notes with your other event paperwork.',
                    'Book any lead-time treatments — smoothing, colour or a facial course — as soon as the plan is agreed.',
                    'Tell us promptly if the date, outfit or plan changes so we can re-schedule around it.',
                ],
            ],
        ];
    }
}
