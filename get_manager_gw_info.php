<?php

include('fetch.php');
$conn = include('db_connection.php');
createTable($conn);

$currentGameweek = getCurrentGameWeek($conn);

$leagueId = '788980';
$url = 'https://fantasy.premierleague.com/drf/leagues-classic-standings/'.$leagueId;
$resp = fetch($url);
$jResp = json_decode($resp);

//Get results array which is in the jResp object and store in list managers
$listManagers = $jResp -> standings -> results;

for ($i = 0; $i < count($listManagers); $i++) {
    $id = $listManagers[$i] -> entry;
    $gameweekNum = 1;
    $gameweeks_info = array();
    $insert_values = array();
    for($j = 1; $j <= $currentGameweek; $j++) {
        $url = 'https://fantasy.premierleague.com/drf/entry/'.$id.'/event/'.$j.'/picks';
        $response = fetch($url);
        $jResponse = json_decode($response);
        if($response !== "Error: 404") {
            $entryHistory = $jResponse -> entry_history;
            unset($entryHistory->id);
            unset($entryHistory->movement);
            unset($entryHistory->targets);
            unset($entryHistory->targets);
            unset($entryHistory->entry);
            unset($entryHistory->event);
            $entryHistory -> manager_id = $id;
            $entryHistory -> gameweek_num = $gameweekNum;
            array_push($gameweeks_info, $entryHistory);
            foreach (get_object_vars($entryHistory) as $var => $val) {
                array_push($insert_values, $val);
            }
            ++$gameweekNum;
        } else {
            ++$gameweekNum;
        }
    }
    $arrayObject = get_object_vars($gameweeks_info[0]);
    $objectKeys = array_keys($arrayObject);
    $columnNames = $objectKeys;

    foreach ($gameweeks_info as $gameweek){
        $question_marks[] = '('  . placeholders('?', count($columnNames)) . ')';
    }
    $onDuplicateString = "";
    foreach ($columnNames as $datafield) {
        $onDuplicateString .= $datafield."=VALUES(".$datafield.'),';
    }
    $onDuplicateString = rtrim($onDuplicateString, ',');

    saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString);
    $question_marks = [];

}

function saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString) {
    $conn ->beginTransaction();
    $sql = "INSERT INTO Managers_gw_info (" . implode(",", $columnNames ) . ")
            VALUES " . implode(',', $question_marks) .
        " ON DUPLICATE KEY  UPDATE " . $onDuplicateString;

    print_r($insert_values);
    echo $sql;

    $stmt = $conn->prepare ($sql);
    try {
        $stmt->execute($insert_values);
    } catch (PDOException $e){
        echo $e->getMessage();
    }
    $conn->commit();
}

function getCurrentGameWeek($conn) {
    $query = $conn->prepare("SELECT id FROM Gameweeks where is_current = 1");
    $query->execute();
    $query->setFetchMode(PDO::FETCH_OBJ);
    $aData = $query->fetch();
    return $aData -> id;
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
/*
 * "entry_history": {
"id": 41069603,
"movement": "new",
"points": 62,
"total_points": 521,
"rank": 1129725,
"rank_sort": 1141016,
"overall_rank": 179520,
"event_transfers": 1,
"event_transfers_cost": 0,
"value": 1020,
"points_on_bench": 3,
"bank": 5,
"entry": 2403953,
"event": 9
}*/
function createTable($conn){
    $sql = "CREATE TABLE IF NOT EXISTS Managers_gw_info(
              points INTEGER NOT NULL,
              total_points INTEGER NOT NULL,
              rank INTEGER NOT NULL,
              rank_sort INTEGER NOT NULL,
              overall_rank INTEGER NOT NULL,
              event_transfers INTEGER NOT NULL,
              event_transfers_cost INTEGER NOT NULL,
              value INTEGER NOT NULL,
              points_on_bench INTEGER NOT NULL,
              bank INTEGER NOT NULL,
              gameweek_num INTEGER NOT NULL,
              manager_id INTEGER NOT NULL,
               PRIMARY KEY (manager_id, gameweek_num))";

    $conn->exec($sql);
}

?>