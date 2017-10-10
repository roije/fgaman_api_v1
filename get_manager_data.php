<?php

$leagueId = '788980';
$url = 'https://fantasy.premierleague.com/drf/leagues-classic-standings/'.$leagueId;
$resp = fetch($url);
$jResp = json_decode($resp);

$conn = getDBConnection();
createTable($conn);
//Column names in table
$columnNames = array('id', 'firstName', 'lastName', 'points', 'teamValue');

//Get results array which is in the jResp object and store in list managers
$listManagers = $jResp -> standings -> results;

//Loop through the listManagers list and fetch info about every manager by getting the entry ID and
//using a Fantasy api endpoint
//Construct a array object with the buildManagerArrayObject function, then add every value
//of managerObjectArray to insert values array.
$insert_values = array();
foreach ($listManagers as $manager) {
    $id = $manager->entry;
    $url = 'https://fantasy.premierleague.com/drf/entry/' . $id;
    $resp = fetch($url);
    $managerArrayObject = buildManagerArrayObject($resp, $id);

    foreach ($managerArrayObject as $managerObject) {
        $question_marks[] = '('  . placeholders('?', sizeof($managerObject)) . ')';
        $insert_values = array_merge($insert_values, array_values($managerObject));
    }
}

//Create ON DUPLICATE KEY string which will be used in query.
//Pattern: name = VALUES(name);
$onDuplicateString = "";
foreach ($columnNames as $datafield) {
    $onDuplicateString .= $datafield."=VALUES(".$datafield.'),';
}
$onDuplicateString = rtrim($onDuplicateString, ',');

//Call the save or update function with the variables above as parameters
saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString);


function buildManagerArrayObject($manager, $id) {
    $manager = json_decode($manager);

    $firstName = $manager -> entry -> player_first_name;
    $lastName = $manager -> entry -> player_last_name;
    $points = $manager -> entry -> summary_overall_points;
    $teamValue = $manager -> entry -> value + $manager -> entry -> bank;

    $managerArrayObject[] = array('id' => $id,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'points' => $points,
        'teamValue' => $teamValue);

    return $managerArrayObject;

}

function saveOrUpdateInDatabase($conn, $columnNames, $insert_values, $question_marks, $onDuplicateString) {
    $conn ->beginTransaction();
    $sql = "INSERT INTO Managers (" . implode(",", $columnNames ) . ") 
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

function fetch($url) {
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url
    ));

    // Send the request & save response to $resp
    $resp = curl_exec($curl);

    // Close request to clear up some resources
    curl_close($curl);

    return $resp;

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
    $sql = "CREATE TABLE IF NOT EXISTS Managers(
        id INT NOT NULL,
        firstName VARCHAR(255),
        lastName VARCHAR(255),
        points VARCHAR(255),
        teamValue VARCHAR(255),
        PRIMARY KEY (id));";

    $conn->exec($sql);
}

function getDBConnection() {
    $servername = "localhost";
    $username = "root";
    $password = "root";
    $db = "fantasy_gaman_v1";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        // set the PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    }
    catch(PDOException $e)
    {
        echo "Connection failed: " . $e->getMessage();
    }
}
?>