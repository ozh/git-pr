<?php 
/**
 * Usage: git pr [OPTION] [PR]
 * Easily pull the content of pull request [PR] in a new branch, with
 * or without committing the proposed changes.
 *
 * The following option can be used to prevent committing
 *  -n, --nocommit      pull PR but don't commit changes
 *  -h, --help          display the help message
 * 
 * For more details, please see the readme file.
 */
class Ozh_Git_PR{
    
    /**
     * Version of this class
     *
     * @var string
     */
    public $version = '1.1';

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
     * Do we want to commit changes or not
     *
     * @var bool
     */
    public $commit;
    
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
        $this->get_params();
        $this->set_owner_repo();
        $this->set_pr_repo();
        $this->pull_and_maybe_commit();
    }
    
    /**
     *  Get the content of this file comment header and send it to print help
     */
    function help() {
        $lines      = file(__FILE__);
        $in_comment = false;
        $help       = '';
        
        // Read lines of this very file to get the comment header
        foreach($lines as $line) {
            // when we first encounter a line starting with '/**', this means we're now in the header
            if( !$in_comment && preg_match('|^\s*/\*\*|', $line) ) {
                $in_comment = true;
                continue;
            }
            
            // if we're in the header and encounter a line with '*/', stop reading
            if( $in_comment && preg_match('|\*/|', $line) ) {
                unset($lines);
                break;
            }
            
            if( $in_comment ) {
                $help .= preg_replace('/^\s*\*\s/', '', $line);
            }
        }
            
        $this->error_and_die($help);
    }
    
    /**
     *  Get parameters from command line
     */
    function get_params() {
        // read arguments from command line
        $args     = getopt("nh", array('nocommit,help'), $optind);
        $nocommit = isset($args['n']) || isset($args['nocommit']);
        $help     = isset($args['h']) || isset($args['help']);
        $pr       = array_slice($_SERVER['argv'], $optind);
        
        // we must have ONE trailing parameter, and it must be numeric
        if( count($pr) != 1 || !ctype_digit($pr[0]) ) {
            $pr = false;
        }
        
        if($help or !$pr) {
            $this->help();
            // this will die here
        }
        
        $this->commit = !$nocommit;
        $this->pr = (int)$pr[0];
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
        curl_setopt( $ch, CURLOPT_USERAGENT,'PHP Ozh_Git_PR ' . $this->version );
        $result = curl_exec( $ch );
        curl_close( $ch );
        return $result;
    }
    
    /**
     * Sets the owner and repo name of the current repo we're in
     *
     * This gets the first fetch repo URL named "origin"
     *
     */
    function set_owner_repo( ) {
        $remote = $this->exec_and_maybe_continue( "git remote get-url origin", false );
        // will get something like: array('https://github.com/YOURLS/YOURLS.git')
        preg_match( "/github.com\/(.*)\/(.*)/", $remote[0], $matches );
        if( count($matches) == 3 ) {
            $this->owner = $matches[1];
            $this->repo  = trim($matches[2], '.git');
        } else {
            $this->error_and_die( "Could not find Github owner and repo" );
        }
    }
    
    
    /**
     * Sets the URL and branch of the PR repo
     *
     */
    function set_pr_repo( ) {
        $url = sprintf( "https://api.github.com/repos/%s/%s/pulls/%d", $this->owner, $this->repo, $this->pr );
                
        $json = $this->get_url( $url );
        
        if( $json == false ) {
            $this->error_and_die( "Could not read info from Github API" );
        }
        
        $json = json_decode( $json );
        
        if( !isset( $json->head->repo->clone_url ) or !isset( $json->head->ref ) ) {
            $this->error_and_die( sprintf( "Could not find info for PR #%s (maybe PR repo was deleted?)\n", $this->pr ) );
        }
        
        $this->remote_url    = $json->head->repo->clone_url;
        $this->remote_branch = $json->head->ref;
        $this->base_branch   = $json->base->ref;
    }

    /**
     * Creates the new branch and git pull the PR without committing
     *
     */
    function pull_and_maybe_commit( ) {
        // git checkout -b pr-1337 some_branch
        // git pull (--no-commit) https://github.com/SOMEDUDE/SOMEFORK.git SOMEBRANCH
        
        $commit = $this->commit === true ? '' : '--no-commit';
        
        $this->exec_and_maybe_continue( sprintf( "git checkout -b pr-%s %s", $this->pr, $this->base_branch ) );
        $this->exec_and_maybe_continue( sprintf( "git pull %s %s %s", $commit, $this->remote_url, $this->remote_branch ) );
    }

    /**
     * Displays message and dies
     *
     * @param string $error    error message
     * @param string $die_msg  optional additional message (eg "script aborted")
     */
    function error_and_die( $error, $die_msg = '' ) {
        echo trim($error) . "\n";
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
        exec( escapeshellcmd( $cmd ) . " 2>&1", $output );
        
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
