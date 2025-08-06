<?php
$candidateSkills = ["php", "mysql"];
$jobs = [
    ["id" => 1, "skills" => ["php", "javascript"]]
];

$data = json_encode([
    "candidate_skills" => $candidateSkills,
    "jobs" => $jobs
]);

$options = [
    'http' => [
        'header'  => "Content-type: application/json",
        'method'  => 'POST',
        'content' => $data,
    ]
];
$context  = stream_context_create($options);
$response = file_get_contents("http://127.0.0.1:5000/match_skills", false, $context);

echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>
