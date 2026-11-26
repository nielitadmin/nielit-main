<?php

require_once __DIR__ . '/../includes/portal_assistant_helper.php';

function assertStep4Condition(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

assertStep4Condition(
    saarthi_step4_is_done_command('Done (proceed to Step 5)'),
    'The Step 4 Done quick reply should advance the registration.'
);
assertStep4Condition(
    saarthi_step4_is_done_command('Done'),
    'A plain Done message should remain supported.'
);
assertStep4Condition(
    !saarthi_step4_is_done_command('Add 12th details'),
    'Adding 12th details must not be interpreted as finishing Step 4.'
);

$registration = [
    'exams' => saarthi_parse_exam_entries('10th, CBSE, 2017, General, 80%'),
];
$registration = saarthi_merge_step4_exam_input(
    $registration,
    '12th, CBSE, 2019, Science, 85%'
);

assertStep4Condition(count($registration['exams']) === 2, '12th should be appended as a second exam.');
assertStep4Condition(
    $registration['exams'][0]['exam_passed'] === '10th'
        && $registration['exams'][0]['year_of_passing'] === '2017',
    'Adding 12th must preserve the saved 10th details.'
);
assertStep4Condition(
    $registration['exams'][1]['exam_passed'] === '12th'
        && $registration['exams'][1]['institute_name'] === 'CBSE'
        && $registration['exams'][1]['year_of_passing'] === '2019'
        && $registration['exams'][1]['stream'] === 'Science'
        && $registration['exams'][1]['percentage'] === '85%',
    'The 12th exam details should be stored in the new exam row.'
);

$context = portal_assistant_site_context();
$context['assistant_name'] = 'Gyansetu';
$systemPrompt = saarthi_system_prompt('en', $context);
assertStep4Condition(
    strpos($systemPrompt, 'You are **Gyansetu**') !== false
        && strpos($systemPrompt, 'Vani') === false,
    'The AI prompt should use the configured chatbot name.'
);

echo "Portal assistant regression checks passed.\n";
