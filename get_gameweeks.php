<?php
$url = 'https://fantasy.premierleague.com/drf/bootstrap-static';
include('fetch.php');
$conn = include('db_connection.php');
createTable($conn);
$resp = fetch($url);
$jResp = json_decode($resp);
$gameweeks = $jResp -> events;

$insert_values = array();
foreach ($gameweeks as $gameweek) {

    $gameweekArrayObject = buildGameweekArrayObject($gameweek);
    $question_marks[] = '('  . placeholders('?', sizeof($gameweekArrayObject)) . ')';
    $insert_values = array_merge($insert_values, array_values($gameweekArrayObject));
}

//Create ON DUPLICATE KEY string which will be used in query.
//Pattern: name = VALUES(name);
$columnNames = array_keys(buildGameWeekArrayObject($gameweeks[0]));

$onDuplicateString = "";
foreach ($columnNames as $datafield) {
    $onDuplicateString .= $datafield."=VALUES(".$datafield.'),';
}
$onDuplicateString = rtrim($onDuplicateString, ',');

saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString);

function saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString) {
    $conn ->beginTransaction();
    $sql = "INSERT INTO Gameweeks (" . implode(",", $columnNames ) . ")
            VALUES " . implode(',', $question_marks) .
        " ON DUPLICATE KEY  UPDATE " . $onDuplicateString;
    $stmt = $conn->prepare ($sql);
    try {
        $stmt->execute($insert_values);
    } catch (PDOException $e){
        echo $e->getMessage();
    }
    $conn->commit();
}

function buildGameWeekArrayObject($gameweek){

    $gameweekArrayObject = array('id' => $gameweek -> id,
        'deadline_time' => $gameweek -> deadline_time,
        'highest_score' => $gameweek -> highest_score,
        'is_previous' => $gameweek -> is_previous,
        'is_current' => $gameweek -> is_current,
        'is_next' => $gameweek -> is_next
        );
    return $gameweekArrayObject;
}

function placeholders($text, $dataFieldsCount, $separator=","){
    $result = array();
    if($dataFieldsCount > 0){
        for($x=0; $x<$dataFieldsCount; $x++){
            $result[] = $text;
        }
    }
    return implode($separator, $result);
}

function createTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS Gameweeks(
        id INT NOT NULL,
        deadline_time TIMESTAMP,
        highest_score INTEGER,
        is_previous TINYINT(1),
        is_current TINYINT(1),
        is_next TINYINT(1),
        PRIMARY KEY (id));";

    $conn->exec($sql);
}