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
    
    public function getWorkspaces() {
        return $this->makeRequest('https://api.clickup.com/api/v2/team');
    }
    
    public function getSpaces($team_id) {
        return $this->makeRequest('https://api.clickup.com/api/v2/team/' . $team_id . '/space');
    }
    
    public function getFolders($space_id) {
        return $this->makeRequest('https://api.clickup.com/api/v2/space/' . $space_id . '/folder');
    }
    
    public function getLists($folder_id) {
        return $this->makeRequest('https://api.clickup.com/api/v2/folder/' . $folder_id . '/list');
    }
    
    public function getTasks($list_id) {
        return $this->makeRequest('https://api.clickup.com/api/v2/list/' . $list_id . '/task');
    }
    
    public function getTimeTracking($task_id) {
        return $this->makeRequest('https://api.clickup.com/api/v2/task/' . $task_id . '/time');
    }
    
    public function getListTasksWithTimeTracking($list_id) {
        $tasks = $this->getTasks($list_id);
        foreach ($tasks['tasks'] as &$task) {
            $timeTracking = $this->getTimeTracking($task['id']);
            $task['time_tracking'] = $timeTracking;
        }
        return $tasks['tasks'];
    }
    
    public function getWorkspacesStructure() {
        $workspaces = $this->getWorkspaces();
        $result = [];
        
        foreach ($workspaces['teams'] as $workspace) {
            $spaces = $this->getSpaces($workspace['id']);
            
            $workspaceData = [
                'workspace' => $workspace['name'],
                'spaces' => []
            ];
            
            foreach ($spaces['spaces'] as $space) {
                $folders = $this->getFolders($space['id']);
                
                foreach ($folders['folders'] as $folder) {
                    $lists = $this->getLists($folder['id']);
                    
                    foreach ($lists['lists'] as $list) {
                        $tasks = $this->getListTasksWithTimeTracking($list['id']);
                        $workspaceData['spaces'][] = [
                            'space' => $space['name'],
                            'folder' => $folder['name'],
                            'list' => $list['name'],
                            'tasks' => $tasks
                        ];
                    }
                }
            }
            
            $result[] = $workspaceData;
        }
        
        return $result;
    }
}
// Función para imprimir los resultados en formato HTML
function printFormattedHTML($data) {
    echo "<h1>ClickUp Workspaces</h1>";
    
    foreach ($data as $workspace) {
        echo "<h2>Workspace: " . htmlspecialchars($workspace['workspace']) . "</h2>";
        foreach ($workspace['spaces'] as $space) {
            echo "<h3>Space: " . htmlspecialchars($space['space']) . "</h3>";
            echo "<h4>Folder: " . htmlspecialchars($space['folder']) . "</h4>";
            echo "<h5>List: " . htmlspecialchars($space['list']) . "</h5>";
            echo "<ul>";
            foreach ($space['tasks'] as $task) {
                echo "<li>";
                echo "<strong>Task:</strong> " . htmlspecialchars($task['name']) . "<br>";
                echo "<strong>Estimated Time:</strong> " . ($task['time_estimate'] / 60000) . " minutes<br>";
                if (isset($task['time_tracking']['data'])) {
                    foreach ($task['time_tracking']['data'] as $time_entry) {
                        echo "<strong>Tracked Time:</strong> " . ($time_entry['duration'] / 60000) . " minutes<br>";
                        echo "<strong>User:</strong> " . htmlspecialchars($time_entry['user']['username']) . "<br>";
                    }
                } else {
                    echo "<strong>Tracked Time:</strong> No tracked time<br>";
                }
                echo "</li>";
            }
            echo "</ul>";
        }
    }
    
}
// Uso de la clase ClickUpAPI
// var API_KEY env
require '.env';

$clickUp = new ClickUpAPI($API_KEY);
$workspacesStructure = $clickUp->getWorkspacesStructure();
printFormattedHTML($workspacesStructure);
