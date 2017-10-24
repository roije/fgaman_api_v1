<?php

include('fetch.php');
$conn = include('db_connection.php');
createTable($conn);

$aPlayers = getPlayersId($conn);
foreach ($aPlayers as $aPlayer) {
    $insert_values = array();
    $playerId = $aPlayer -> id;
    $url = 'https://fantasy.premierleague.com/drf/element-summary/' . $playerId;
    $resp = fetch($url);
    $jResp = json_decode($resp);
    $playerGameweekHistoryList = $jResp -> history;
    $columnCount = 0;
    foreach ($playerGameweekHistoryList as $gameweek) {
        array_push($insert_values, $playerId);
        $columnCount = count(get_object_vars($gameweek));
        foreach (get_object_vars($gameweek) as $var => $val) {
            array_push($insert_values, $val);
        }
        $question_marks[] = '('  . placeholders('?', $columnCount + 1) . ')';
    }
    $arrayPlayerGameweekObject = (array) $playerGameweekHistoryList[0];
    $arrayPlayerGameweekObject = array('player_id' => $playerId) + $arrayPlayerGameweekObject;
    $columnNames = array_keys($arrayPlayerGameweekObject);
    $onDuplicateString = "";
    foreach ($columnNames as $datafield) {
        $onDuplicateString .= $datafield."=VALUES(".$datafield.'),';
    }
    $onDuplicateString = rtrim($onDuplicateString, ',');

    saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString);
    $question_marks = [];
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

function getPlayersId($conn) {
    $query = $conn->prepare("SELECT id FROM Players");
    $query->execute();
    $query->setFetchMode(PDO::FETCH_OBJ);
    $aData = $query->fetchAll();
    return $aData;
}


function saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString) {
    $conn ->beginTransaction();
    $sql = "INSERT INTO Players_games (" . implode(",", $columnNames ) . ")
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

function createTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS Players_games(
      player_id INTEGER NOT NULL,
      id INTEGER NOT NULL,
      kickoff_time TIMESTAMP,
      kickoff_time_formatted VARCHAR(100),
      team_h_score INTEGER,
      team_a_score INTEGER,
      was_home INTEGER,
      round INTEGER,
      total_points INTEGER,
      value INTEGER,
      transfers_balance INTEGER,
      selected INTEGER,
    transfers_in INTEGER,
    transfers_out INTEGER,
    loaned_in INTEGER,
    loaned_out INTEGER,
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
    bps INTEGER,
    influence DOUBLE,
    creativity DOUBLE,
    threat DOUBLE,
    ict_index  DOUBLE,
    ea_index  INTEGER,
    open_play_crosses INTEGER,
    big_chances_created INTEGER,
    clearances_blocks_interceptions INTEGER,
    recoveries INTEGER,
    key_passes INTEGER,
    tackles INTEGER,
    winning_goals INTEGER,
    attempted_passes INTEGER,
    completed_passes INTEGER,
    penalties_conceded INTEGER,
    big_chances_missed INTEGER,
    errors_leading_to_goal INTEGER,
    errors_leading_to_goal_attempt INTEGER,
    tackled INTEGER,
    offside INTEGER,
    target_missed INTEGER,
    fouls INTEGER,
    dribbles INTEGER,
    element INTEGER,
    fixture INTEGER,
    opponent_team INTEGER,
     PRIMARY KEY (player_id, round))";

    $conn->exec($sql);

}

?>