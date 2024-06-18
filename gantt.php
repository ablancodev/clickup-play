<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClickUp Task Gantt Chart</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

<?php

class ClickUpAPI {
    private $token;
    
    public function __construct($token) {
        $this->token = $token;
    }
    
    private function makeRequest($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: ' . $this->token
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }

    public function getTeams() {
        return $this->makeRequest('https://api.clickup.com/api/v2/team');
    }
    
    public function getLists($team_id) {
        // Example team_id, replace it with your actual team_id
        //$team_id = 'YOUR_TEAM_ID';
        $spaces = $this->makeRequest('https://api.clickup.com/api/v2/team/' . $team_id . '/space');
        $lists = [];
        
        foreach ($spaces['spaces'] as $space) {
            $folders = $this->makeRequest('https://api.clickup.com/api/v2/space/' . $space['id'] . '/folder');
            foreach ($folders['folders'] as $folder) {
                $folderLists = $this->makeRequest('https://api.clickup.com/api/v2/folder/' . $folder['id'] . '/list');
                foreach ($folderLists['lists'] as $list) {
                    $lists[] = [
                        'id' => $list['id'],
                        'name' => $folder['name'] . ' - ' . $list['name']
                    ];
                }
            }
        }
        
        return $lists;
    }
    
    public function getTasks($list_id) {
        return $this->makeRequest('https://api.clickup.com/api/v2/list/' . $list_id . '/task');
    }

    //   CURLOPT_URL => "https://api.clickup.com/api/v2/team/" . teamId . "/time_entries?" . http_build_query($query),
    public function getTimeEntries($team_id, $task_id, $assignes = []) {
        return $this->makeRequest('https://api.clickup.com/api/v2/team/' . $team_id . '/time_entries?task_id=' . $task_id . '&assignee=' . implode(',', $assignes));
    }
}

require '.env';

$clickUp = new ClickUpAPI($API_KEY);

$teams = $clickUp->getTeams();

$team_id = $teams['teams'][0]['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['list_id'])) {
    $list_id = $_POST['list_id'];
    $tasks = $clickUp->getTasks($list_id);
    $taskData = [];

    foreach ($tasks['tasks'] as $task) {
        $progress = null;
        if (isset($task['custom_fields'])) {
            foreach ($task['custom_fields'] as $field) {
                if ($field['name'] === '⏳ Progreso') {
                    $progress = $field['value'];
                    break;
                }
            }
        }

        $assignes = [];
        if (isset($task['assignees'])) {
            foreach ($task['assignees'] as $assigne) {
                var_dump($assigne);
                $assignes[] = $assigne['id'];
            }
        }

        // obtenemos sus entradas de tiempo
        $timeEntries = $clickUp->getTimeEntries($team_id, $task['id'], $assignes);

        $taskData[] = [
            'name' => $task['name'],
            'start_date' => isset($task['start_date']) ? $task['start_date'] : null,
            'due_date' => isset($task['due_date']) ? $task['due_date'] : null,
            'progress' => $progress['percent_completed'],
            'time_entries' => $timeEntries['data']
        ];
    }

    echo "<div class='bg-white p-6 rounded-lg shadow-md'>";
    echo "<h2 class='text-xl font-bold mb-4'>Gantt Chart for List ID: " . htmlspecialchars($list_id) . "</h2>";
    echo "<div class='space-y-4'>";

    foreach ($taskData as $task) {
        if ($task['start_date'] && $task['due_date']) {
            $start = date('Y-m-d', $task['start_date'] / 1000);
            $end = date('Y-m-d', $task['due_date'] / 1000);
            $duration = (strtotime($end) - strtotime($start)) / (60 * 60 * 24);
            $progressWidth = isset($task['progress']) ? $task['progress'] : 0;
            $timeEntries = $task['time_entries'];

            echo "<div>";
            echo "<div class='font-semibold'>" . htmlspecialchars($task['name']) . "</div>";
            echo "<div class='relative pt-1'>";
            echo "<div class='overflow-hidden h-2 mb-4 text-xs flex rounded bg-blue-200'>";
            echo "<div style='width:" . ($progressWidth) . "%' class='shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500'></div>";
            echo "</div>";
            echo "<div class='text-gray-600'>" . $start . " to " . $end . "</div>";

            // Display time entries
            //echo "<div class='mt-2'>";
            //echo "<div class='font-semibold'>Time Entries</div>";
            //echo "<ul>";
            // totales por usuario
            $totalTime = [];
            foreach ($timeEntries as $timeEntry) {
                if (!isset($totalTime[$timeEntry['user']['username']])) {
                    $totalTime[$timeEntry['user']['username']] = 0;
                }
                $totalTime[$timeEntry['user']['username']] += $timeEntry['duration'];
                //echo "<li>";
                //echo "<strong>User:</strong> " . htmlspecialchars($timeEntry['user']['username']) . "<br>";
                //echo "<strong>Duration:</strong> " . intval($timeEntry['duration'] / 60000) . " minutes<br>";
                //echo "</li>";
            }
            //echo "</ul>";
            //echo "</div>";

            // totales
            echo "<div class='mt-2'>";
            echo "<div class='font-semibold'>Total Time</div>";
            echo "<ul>";
            foreach ($totalTime as $user => $time) {
                echo "<li>";
                echo "<strong>User:</strong> " . htmlspecialchars($user) . "<br>";
                echo "<strong>Total Time:</strong> " . intval($time / 60000) . " minutes<br>";
                // costes indicando un precio por hora de 30€
                echo "<strong>Cost (30€ x hour):</strong> " . intval($time / 60000 / 60 * 30) . "€<br>";
                echo "</li>";
            }
            echo "</ul>";
            echo "</div>";

            echo "</div>";
            echo "</div>";
        }
    }

    echo "</div>";
    echo "</div>";
}

// Display the form
$lists = $clickUp->getLists($teams['teams'][0]['id']);

?>

<div class="bg-white p-6 rounded-lg shadow-md max-w-md mx-auto">
    <h1 class="text-2xl font-bold mb-4">Select a List</h1>
    <form method="post" action="">
        <div class="mb-4">
            <label for="list_id" class="block text-gray-700 text-sm font-bold mb-2">List:</label>
            <select name="list_id" id="list_id" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                <?php
                foreach ($lists as $list) {
                    echo "<option value='" . htmlspecialchars($list['id']) . "'>" . htmlspecialchars($list['name']) . "</option>";
                }
                ?>
            </select>
        </div>
        <div class="flex items-center justify-between">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">Submit</button>
        </div>
    </form>
</div>

</body>
</html>
