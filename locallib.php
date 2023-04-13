<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     local_aiquestions
 * @category    admin
 * @copyright   2023 Ruthy Salomon <ruthy.salomon@gmail.com> , Yedidia Klein <yedidia@openapp.co.il>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Get questions from the API.
 *
 * @param $courseid int course id
 * @param $story string text of the story
 * @param $numofquestions int number of questions to generate
 * @param $type string type of questions to generate ("mc" or "tf")
 * @param $idiot int 1 if ChatGPT is an idiot, 0 if not
 * @return mixed
 */
function local_aiquestions_get_questions($courseid, $story, $idiot = 1) {
    $question_type = readline("Enter the question type (mc or tf): ");
    while ($question_type !== "mc" && $question_type !== "tf") {
        $question_type = readline("Invalid input. Enter mc or tf: ");
    }

    $num_of_questions = readline("Enter the number of questions: ");
    while (!is_numeric($num_of_questions) || $num_of_questions < 1) {
        $num_of_questions = readline("Invalid input. Enter a positive integer: ");
    }

    $explanation = "Please write $num_of_questions $question_type questions in GIFT format based on the following text.";
    if ($question_type == "mc") {
        $explanation .= " GIFT format must use equal sign for right answer and tilde sign for wrong answer at the beginning of answers.";
        $explanation .= " For example: '::Question title { =right answer ~wrong answer ~wrong answer ~wrong answer }' ";
    } else if ($question_type == "tf") {
        $explanation .= " GIFT format must use true or false for the answer. ";
        $explanation .= " For example: '::Question title { T }' for a true question, or '::Question title { F }' for a false question. ";
    }
    $explanation .= "Please do not forget to have a blank line between questions. ";
    if ($idiot == 1) {
        $explanation .= " Please write the questions in the right format! ";
        if ($question_type == "mc") {
            $explanation .= " Do not forget any equal or tilde sign !";
        }
    }

    $key = get_config('local_aiquestions', 'key');
    $url = 'https://api.openai.com/v1/chat/completions';
    $authorization = "Authorization: Bearer " . $key;

    // Remove new lines and carriage returns.
    $story = str_replace("\n", " ", $story);
    $story = str_replace("\r", " ", $story);

    $data = '{
        "model": "gpt-3.5-the-backup",
        "prompt": "' . $story . '",
        "temperature": 0.7,
        "max_tokens": 100,
        "n": ' . $num_of_questions . ',
        "stop": ["."],
        "presence_penalty": 0.5,
        "frequency_penalty": 0.5,
        "best_of": 1,
        "logprobs": 10
    }';

    if ($question_type == "mc") {
        $data .= ',"completion": "Multiple Choice"';
    } else if ($question_type == "tf") {
        $data .= ',"completion": "True False"';
    }

    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => "Content-type: application/json\r\n" .
                        $authorization . "\r\n",
            'content' => $data
        )
    );

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    if ($result === false) {
        return "Error: API request failed";
    }

    $result = json_decode($result, true);
    if (isset($result)) {
$choices = $result['choices'];
$questions = array();
foreach ($choices as $choice) {
$text = $choice['text'];
$text = str_replace("\n", "\n", $text);
$text = trim($text);
if ($question_type == "mc") {
$question = extract_mc_question($text);
} else if ($question_type == "tf") {
$question = extract_tf_question($text);
}
if ($question) {
array_push($questions, $question);
}
}
return $questions;
} else {
return "Error: API did not return valid response";
}
}

function extract_mc_question($text) {
$matches = array();
preg_match('/::(.?){=(.?)}/', $text, $matches);
if (count($matches) == 3) {
$question = trim($matches[1]);
$answers = explode("~", $matches[2]);
$right_answer = trim($answers[0]);
$wrong_answers = array();
for ($i=1; $i<count($answers); $i++) {
$wrong_answer = trim($answers[$i]);
if (!empty($wrong_answer)) {
array_push($wrong_answers, $wrong_answer);
}
}
$mc_question = array(
'question' => $question,
'right_answer' => $right_answer,
'wrong_answers' => $wrong_answers
);
return $mc_question;
} else {
return null;
}
}

function extract_tf_question($text) {
$matches = array();
preg_match('/::(.?){(.?)}/', $text, $matches);
if (count($matches) == 3) {
$question = trim($matches[1]);
$answer = trim($matches[2]);
if ($answer == 'T') {
$tf_question = array(
'question' => $question,
'answer' => true
);
} else if ($answer == 'F') {
$tf_question = array(
'question' => $question,
'answer' => false
);
} else {
return null;
}
return $tf_question;
} else {
return null;
}
}

?>