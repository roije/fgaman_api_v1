<?php
$url = 'https://fantasy.premierleague.com/drf/bootstrap-static';
include('fetch.php');
$conn = include('db_connection.php');
createTable($conn);

$resp = fetch($url);
$jResp = json_decode($resp);
$players = $jResp -> elements;


$insert_values = array();
foreach ($players as $player) {

    $playerArrayObject = buildPlayerArrayObject($player);
    $question_marks[] = '('  . placeholders('?', sizeof($playerArrayObject)) . ')';
    $insert_values = array_merge($insert_values, array_values($playerArrayObject));
}

//Create ON DUPLICATE KEY string which will be used in query.
//Pattern: name = VALUES(name);
$columnNames = array_keys(buildPlayerArrayObject($players[0]));

$onDuplicateString = "";
foreach ($columnNames as $datafield) {
    $onDuplicateString .= $datafield."=VALUES(".$datafield.'),';
}
$onDuplicateString = rtrim($onDuplicateString, ',');

saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString);

function saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString) {

    $conn ->beginTransaction();
    $sql = "INSERT INTO Players (" . implode(",", $columnNames ) . ")
            VALUES " . implode(',', $question_marks) .
        " ON DUPLICATE KEY  UPDATE " . $onDuplicateString;
    $stmt = $conn->prepare ($sql);
    echo $sql;
    try {
        $stmt->execute($insert_values);
    } catch (PDOException $e){
        echo $e->getMessage();
    }
    $conn->commit();
}

function buildPlayerArrayObject($player) {

    $managerArrayObject = array('id' => $player -> id,
        'first_name' => $player -> first_name,
        'last_name' => $player -> second_name,
        'price' => $player -> now_cost,
        'photo' => buildImageString( $player -> photo),
        'dreamteam_count' => $player -> dreamteam_count,
        'total_points' => $player -> total_points,
        'points_per_game' => $player -> points_per_game,
        'position' => $player -> element_type,
        'team' => $player -> team,
        'minutes' => $player -> minutes,
        'goals_scored' => $player -> goals_scored,
        'assists' => $player -> assists,
        'clean_sheets' => $player -> clean_sheets,
        'goals_conceded' => $player -> goals_conceded,
        'own_goals' => $player -> own_goals,
        'penalties_saved' => $player -> penalties_saved,
        'penalties_missed' => $player -> penalties_missed,
        'yellow_cards' => $player -> yellow_cards,
        'red_cards' => $player -> red_cards,
        'saves' => $player -> saves,
        'bonus' => $player -> bonus,
        );
    return $managerArrayObject;
}

function createTable($conn){
    $sql = "CREATE TABLE IF NOT EXISTS Players(
              id INTEGER NOT NULL,
              first_name VARCHAR(255),
              last_name VARCHAR(255),
              price INTEGER,
              photo VARCHAR(255),
              dreamteam_count INTEGER,
              total_points INTEGER,
              points_per_game VARCHAR(255),
              position INTEGER,
              team INTEGER,
              minutes INTEGER,
              goals_scored INTEGER,
              assists INTEGER,
              clean_sheets INTEGER,
              goals_conceded INTEGER,
              own_goals INTEGER,
              penalties_saved INTEGER,
              penalties_missed INTEGER,
              yellow_cards INTEGER,
              red_cards INTEGER,
              saves INTEGER,
              bonus INTEGER,
               PRIMARY KEY (id))";

    $conn->exec($sql);
}

function buildImageString($image) {
    $imageName = explode('.', $image)[0];
    $image = 'http://platform-static-files.s3.amazonaws.com/premierleague/photos/players/110x140/p'.$imageName.'.png';
    return $image;
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