<?php 

$repoUrl = 'https://github.com/EDUCAlliance/EDUC-AI-Chatbot.git';
// Working directory: the current directory where update_repo.php is located
$localPath = __DIR__;

chdir($localPath);

// Check if the directory is already initialized as a Git repository
if (!is_dir($localPath . '/.git')) {
    echo "Initializing Git repository...\n";
    
    // Initialize the Git repository
    $initOutput = shell_exec('git init 2>&1');
    
    // Add the remote "origin"
    $remoteOutput = shell_exec("git remote add origin $repoUrl 2>&1");
    
    // Fetch data from the remote repository
    $fetchOutput = shell_exec("git fetch origin 2>&1");
    
    // Choose the branch: assume 'master' initially; if 'main' exists, then use it
    $branch = 'master';
    $remoteBranches = shell_exec("git branch -r");
    if (strpos($remoteBranches, 'origin/main') !== false) {
        $branch = 'main';
    }
    
    // Reset the local repository to match the remote branch state
    $resetOutput = shell_exec("git reset --hard origin/$branch 2>&1");
    
    echo "Repository initialized and data fetched:\n";
    echo $initOutput . $remoteOutput . $fetchOutput . $resetOutput;
} else {
    // If the repository already exists, pull updates from the remote repository
    $pullOutput = shell_exec("git pull 2>&1");
    echo "Repository updated:\n" . $pullOutput;
}
?>


