<?php 
    /*
     * On app engine, processes a task queue
     */

    include "init.php";
    include "asana.php";

    try {
        if (isCancelled($channel)) {
            pre("User cancelled job", null, "info");
            return;
        }

        // Read task parameters
        $targetWorkspaceId = $_POST['targetWorkspaceId'];
        $workspaceId = $_POST['workspaceId'];
        $projects = $_POST['projects'];

        $teamId = postDefault('teamId', false);
        $customFieldMapping = postDefault('customFieldMapping');

        $projectOffset = postDefault('projectOffset', 0);
        $targetProject = postDefault('targetProject');

        $sectionPage = postDefault('sectionPage');
        $sectionOffset = postDefault('sectionOffset', 0);
        $targetSection = postDefault('targetSection');
        $lastSectionId = postDefault('lastSectionId');

        $taskPage = postDefault('taskPage');
        $taskOffset = postDefault('taskOffset', 0);
        $lastTaskId = postDefault('lastTaskId');

        $nextSectionPage = null;
        $nextTaskPage = null;

        // Get some info
        $team = null;
        $targetWorkspace = getWorkspace($targetWorkspaceId);
        $copyTags = !isPersonalProjects($targetWorkspace);
        $copyAttachments = true;

        if (isOrganisation($targetWorkspace)) {
            if ($teamId) {
                $team = getTeam($targetWorkspaceId, $teamId);
            }
        }

        $teamName = '';
        if ($team) {
            $teamName = '/' . $team['name'];
        }

        /** Requeue the current task with the current state of the copy */
        function requeue($delay = 60) {
            global $channel, $authToken, $targetWorkspaceId, $workspaceId, $projects, $teamId,
                $projectOffset, $targetProject,
                $sectionPage, $sectionOffset, $targetSection, $lastSectionId,
                $taskPage, $taskOffset, $lastTaskId,
                $copyTags, $copyAttachments, $customFieldMapping, $DEBUG;

            $params = [
                'channel' => $channel,
                'authToken' => $authToken,
                'targetWorkspaceId' => $targetWorkspaceId,
                'workspace' => $workspaceId,
                'projects' => $projects,
                'teamId' => $teamId,
                'customFieldMapping' => $customFieldMapping,

                'projectOffset' => $projectOffset,
                'targetProject' => $targetProject,

                'sectionPage' => $sectionPage,
                'sectionOffset' => $sectionOffset,
                'targetSection' => $targetSection,
                'lastSectionId' => $lastSectionId,

                'taskPage' => $taskPage,
                'taskOffset' => $taskOffset,
                'lastTaskId' => $lastTaskId,

                'copyTags' => $copyTags,
                'copyAttachments' => $copyAttachments,
                'debug' => $DEBUG
            ];
            if ($rateLimit > time()) {
                $delay = $rateLimit - time() + 10;
            }
            $options = ['delay_seconds' => $delay];
            $task = new \google\appengine\api\taskqueue\PushTask('/process/project', $params, $options);
            $task_name = $task->add();
        }

        for (; $projectOffset < count($projects); $projectOffset++) {
            $project = getProject($projects[$projectOffset], false);

            // Create a new project if we're not in the middle of an existing copy
            if ($targetProject) {
                $targetProjectName = $targetProject['name'];
            } else {
                $targetProjectName = $project['name'];

                // Check for an existing project in the target workspace
                $targetProjects = getAllProjects($targetWorkspaceId);
                if ($DEBUG) {
                    pre($targetProjects);
                }

                $count = 2;
                $found = false;
                do {
                    $found = false;
                    for ($j = 0; $j < count($targetProjects); $j++) {
                        if (strcmp($targetProjects[$j]['name'], $targetProjectName) == 0) {
                            $targetProjectName = $project['name'] . ' ' . $count++;
                            $found = true;
                            break;
                        }
                    }
                } while ($found == true && $count < 100);

                // Create target project
                progress('Copying ' . $project['name'] . ' to ' . $targetWorkspace['name'] . $teamName . '/' . $targetProjectName);
                $targetProject = createProject($targetWorkspaceId, $targetProjectName, $teamId, $project);
                if ($customFieldMapping) {
                    addProjectFields($project, $targetProject["id"], $customFieldMapping);
                }
                notifyCreated($targetProject);
            }

            // Run copy
            $fromProjectId = $project['id'];
            $targetProjectId = $targetProject['id'];
            $tasks = array();
            $section = null;
            if ($project['layout'] == "board") {
                // Get sections
                incrementRequests(1);
                $nextSectionPage = $sectionPage;
                $sections = getSections($fromProjectId, $nextSectionPage, 10);

                // Select current section
                $section = $sections[$sectionOffset];
                $sectionId = $section['id'];
                $lastSectionId = $sectionId;

                // Create target section if not provided
                if (!$targetSection) {
                    incrementRequests(1);
                    $targetSection = createSection($targetProjectId, $section['name']);
                }
                
                // Get section tasks
                incrementRequests(1);
                $nextTaskPage = $taskPage;
                $tasks = getSectionTasks($sectionId, $nextTaskPage, 10, $lastTaskId);
            } else {
                // Get Project tasks
                incrementRequests(1);
                $nextTaskPage = $taskPage;
                $tasks = getProjectTasks($fromProjectId, $nextTaskPage, 10, $lastTaskId);
            }

            // copy Tasks
            // TODO check timing and re-queue after 5mins
            // TODO select batch of tasks and perform creation step in parallel
            for (; $taskOffset < count($tasks); $taskOffset++) {
                // Potential calls to the API
                // createTask: 2
                // addToProject: 1
                // copyHistory: 2
                // copySubtasks: 1 + 2N subtasks + descendants

                // Allow for minimum (6), rest will be deferred if not enough allowance
                $pending = incrementRequests(6);
                $rateLimit = getRateLimit();

                if (isCancelled($channel)) {
                    pre("User cancelled job", null, "info");
                    return;
                }

                // Are there too many pending requests?
                if ($pending > 90 || $rateLimit > time()) {
                    // Re-queue task creation at the current point
                    requeue(60);
                    return;
                }

                $task = $tasks[$taskOffset];
                $taskId = $task['id'];
                $lastTaskId = $taskId;

                $newTask = $task;
                unset($newTask['id']);
                $newTask['assignee'] = $newTask['assignee']['id'];
                foreach ($newTask as $key => $value) {
                    if (empty($value)) {
                        unset($newTask[$key]);
                    }
                }
                if ($copyTags && isset($newTask['tags'])) {
                    $newTask['tags'] = getTargetTags($newTask, $targetWorkspaceId);
                } else {
                    unset($newTask['tags']);
                }

                progress("Creating task '" . $newTask['name'] . "'");
                $newTask = createTask($targetWorkspaceId, $newTask, $customFieldMapping);
                if ($targetSection) {
                    addTaskToSection($newTask, $targetProjectId, $targetSection['id']);
                } else {
                    addTaskToProject($newTask, $targetProjectId);
                }

                if ($customFieldMapping && $newTask["custom_fields"]) {
                    try {
                        setCustomFields($newTask["id"], $newTask["custom_fields"]);
                    } catch (Exception $e) {
                        $report = array(
                            task => $newTask,
                            customFieldMapping => $customFieldMapping
                        );
                        fatal($report, "Unable to set custom fields");
                    }
                }
                
                queueTask($targetWorkspaceId, $taskId, $newTask, $copyTags, $copyAttachments, $customFieldMapping, $targetProjectId);
            }

            // Do we have more task pages?
            if (!empty($nextTaskPage)) {
                // Re-queue task creation at the start of the next page
                $taskPage = $nextTaskPage;
                $taskOffset = 0;
                requeue(60);
                return;
            }

            // Reset task paging
            $taskPage = null;
            $taskOffset = 0;

            if ($targetSection) {
                // Do we have more sections in the current page?
                $targetSection = null;
                $sectionOffset ++;
                if ($sectionOffset < count($sections)) {
                    requeue(0);
                    return;
                }

                // Do we have more section pages?
                $sectionOffset = 0;
                if (!empty($nextSectionPage)) {
                    $sectionPage = $nextSectionPage;
                    requeue(0);
                    return;
                }
            }

            // Reset for next project
            $sectionPage = null;
            $targetProject = null;
        }

        $params = [
            'channel' => $channel,
            'authToken' => $authToken
        ];
        $delay = 60;
        $options = ['delay_seconds' => $delay];
        $task = new \google\appengine\api\taskqueue\PushTask('/process/complete', $params, $options);
        $task_name = $task->add();
    } finally {
        flushProgress();
    }
