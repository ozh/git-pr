<?php

/**
 * git pr 1337
 *
 * Creates a new branch named "pr-1337" and pulls everything from the pull request without committing it
 */
class Ozh_Git_PR{
    
    /**
     * Current repo owner
     *
     * @var string
     */
    public $owner;

    /**
     * Current repo name
     *
     * @var string
     */
    public $repo;

    /**
     * Base branch for the PR (often 'master', but you can also make a PR against another branch)
     *
     * @var string
     */
    public $base_branch;

    /**
     * Pull Request ID to pull
     *
     * @var integer
     */
    public $pr;
    
    /**
     * Remote clone URL of the PR
     *
     * @var string
     */
    public $remote_url;

    /**
     * Remote branch of the PR
     *
     * @var string
     */
    public $remote_branch;
    

    /**
     * Class constructor, does everything
     *
     */
    function __construct() {
        $this->set_pr_num();
        $this->set_owner_repo();
        $this->set_pr_repo();
        
        $this->pull_not_commit();
    }
    
    
    /**
     * Sets the PR ID number from the command line argument
     *
     * If incorrect arguments are passed, prints help and dies
     *
     */
    function set_pr_num( ) {
        global $argv;
        // expected usage : "script.php 1337" or "php script.php 1337"
        // First element of $argv will be the script file itself, second will be the PR number

        if( count( $argv ) != 2 or !is_numeric( $argv[1] )) {
            $this->error_and_die( "Usage :\ngit pr [PR number]\nSee README" );
            die();
        }
        
        $this->pr = $argv[1];
        
    }
    
    
    /**
     * Simple curl wrapper
     *
     * Note: SSL verification turned off on purpose
     *
     * @param string $url URL to fetch
     * @return mixed false if error, string if body
     */
    function get_url( $url ) {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER , 1 );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        curl_setopt( $ch, CURLOPT_USERAGENT,'PHP Ozh_Git_PR 1.0' );
        $result = curl_exec( $ch );
        curl_close( $ch );
        return $result;
    }

    
    /**
     * Sets the owner and repo name of the current repo we're in
     *
     * This gets the first fetch repo URL -- assuming this is "origin"
     *
     */
    function set_owner_repo( ) {
        $remotes = $this->exec_and_maybe_continue( "git remote -v", false );
        
        // find the "fetch" repo, eg :
        // "origin\thttps://github.com/YOURLS/YOURLS.git (fetch)"
        $i = 0;
        while( false === strpos( $remotes[ $i ], "fetch" ) ) {
            $i++;
        }
        preg_match( "/github.com\/(.*)\.git/", $remotes[ $i ], $matches );
        list( $owner, $repo ) = explode( "/", $matches[1] );
        
        $this->owner = $owner;
        $this->repo  = $repo;
    }
    
    
    /**
     * Sets the URL and branch of the PR repo
     *
     */
    function set_pr_repo( ) {
        $url = sprintf( "https://api.github.com/repos/%s/%s/pulls/%d", $this->owner, $this->repo, $this->pr );
        
        $json = $this->get_url( $url );
        
        if( $json == false ) {
            echo "\n";
            echo sprintf( "Could not find PR %s\n", $this->pr );
            die();
        }
        
        $json = json_decode( $json );
        
        if( !isset( $json->head->repo->clone_url ) or !isset( $json->head->ref ) ) {
            $this->error_and_die( sprintf( "Could not find PR #%s !", $this->pr ) );
        }
        
        $this->remote_url    = $json->head->repo->clone_url;
        $this->remote_branch = $json->head->ref;
        $this->base_branch   = $json->base->ref;
    }
    
 
    /**
     * Creates the new branch and git pull the PR without committing
     *
     */
    function pull_not_commit( ) {
        // git checkout -b pr-1337 some_branch
        // git pull --no-commit https://github.com/SOMEDUDE/SOMEFORK.git SOMEBRANCH
        
        $this->exec_and_maybe_continue( sprintf( "git checkout -b pr-%s %s", $this->pr, $this->base_branch ) );
        $this->exec_and_maybe_continue( sprintf( "git pull --no-commit %s %s", $this->remote_url, $this->remote_branch ) );
    }
    
    
    /**
     * Displays message and dies
     *
     * @param string $error    error message
     * @param string $die_msg  optional additional message (eg "script aborted")
     */
    function error_and_die( $error, $die_msg = '' ) {
        echo "\n";
        echo "$error\n";
        echo "\n";
        die( $die_msg );
        
    }
    

    /**
     * Execs a command and dies if an error occured
     *
     * @param string  $cmd      command to execute
     * @param bool    $display  if the command output should be displayed or not
     * @return $array  command output
     */
    function exec_and_maybe_continue( $cmd, $display = true ) {
        echo "$cmd\n";
        
        exec( $cmd . " 2>&1", $output );
        
        if( $display ) {
            echo implode( "\n", $output ) . "\n";
        }
        
        if( preg_grep( '/fatal|error/', $output ) ) {
            die( "\nScript aborted !\n" );
        }
        
        return $output;
    }
    
}

// Launch and execute everything
new Ozh_Git_PR;
