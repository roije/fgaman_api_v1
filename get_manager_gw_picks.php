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

for ($j = 0; $j < count($listManagers); $j++) {
    $id = $listManagers[$j] -> entry;
    $url = 'https://fantasy.premierleague.com/drf/entry/' . $id;

    $insert_values = array();
    $gameweekpicks = array();
    $gameweekNum = 1;
    for($i = 1; $i <= $currentGameweek; $i++) {
        $url = 'https://fantasy.premierleague.com/drf/entry/'.$id.'/event/'.$i.'/picks';
        $response = fetch($url);
        $jResponse = json_decode($response);
        if($response !== "Error: 404") {
            $picks = $jResponse -> picks;
            //array_push($picks)
            array_push($gameweekpicks, $picks);
            //$insert_values = array_merge($insert_values, $gameweekpicks);
        } else {
            ++$gameweekNum;
        }
    }

    if(count($gameweekpicks) > 0) {
        for($k = 1; $k <= count($gameweekpicks); $k++) {
            $gameweekpick = $gameweekpicks[$k -1];
            foreach ($gameweekpick as $player) {
                array_push($insert_values, $id);
                array_push($insert_values, $gameweekNum);
                foreach (get_object_vars($player) as $var => $val) {
                    array_push($insert_values, $val);
                }
                $question_marks[] = '('  . placeholders('?', 7) . ')';

            }
            //$count = count((array) $managerGameweekPicksArrayObject['gameweek_picks'][0][0]) + 2;
            $gameweekNum++;
        }

        $managerGameweekPicksArrayObject = buildManagerGameweekPickArrayObject($id, $gameweekpicks);

        $columnNames = array('manager_id', 'gameweek_number', 'player_id', 'position', 'is_captain', 'is_vice_captain', 'multiplier');

        $onDuplicateString = "";
        foreach ($columnNames as $datafield) {
            $onDuplicateString .= $datafield."=VALUES(".$datafield.'),';
        }
        $onDuplicateString = rtrim($onDuplicateString, ',');

        saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString);
    }
    ++$gameweekNum;
    $question_marks = [];

}

function saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString) {
    //INSERT INTO managers_gw_picks (manager_id, gameweek_number, player_id, position, is_captain, is_vice_captain, multiplier) VALUES (3592970, 1, 260, 1, 0, 0, 1), (3592970, 1, 97, 2, 0, 0, 1)

    $conn ->beginTransaction();
    $sql = "INSERT INTO managers_gw_picks (" . implode(",", $columnNames ) . ")
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

function placeholders($text, $dataFieldsCount, $separator=","){
    $result = array();
    if($dataFieldsCount > 0){
        for($x=0; $x<$dataFieldsCount; $x++){
            $result[] = $text;
        }
    }
    return implode($separator, $result);
}


function buildManagerGameweekPickArrayObject($id, $gameweekpicks){
    $managerArrayObject = array('id' => $id,
        'gameweek_picks' => $gameweekpicks);

    return $managerArrayObject;
}

function getCurrentGameWeek($conn) {
    $query = $conn->prepare("SELECT id FROM Gameweeks where is_current = 1");
    $query->execute();
    $query->setFetchMode(PDO::FETCH_OBJ);
    $aData = $query->fetch();
    return $aData -> id;
}

function createTable($conn){
    $sql = "CREATE TABLE IF NOT EXISTS Managers_gw_picks(
              manager_id INTEGER NOT NULL,
              gameweek_number INTEGER NOT NULL,
              player_id INTEGER NOT NULL,
              position INTEGER,
              is_captain TINYINT(1),
              is_vice_captain TINYINT(1),
              multiplier INTEGER,
               PRIMARY KEY (manager_id, gameweek_number, player_id))";

    $conn->exec($sql);
}