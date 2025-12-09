<?php

/**
 * Easily pull the content of pull request #[PR_NUM] in a new branch.
 * Usage: git pr [OPTION] [PR_NUM]
 *
 * Startup:
 *  -l | --list              list all pull requests of the current repo
 *  -c | --cleanup           cleanup remotes of PRs that are no longer open
 *  -v | --version           display the version number
 *  -h | --help              display the help message
 *
 * Pull options:
 *  -n | --nocommit          pull given PR but don't commit changes
 *
 * Branch options:
 *  -b | --branch <branch>   custom local PR branch name (defaults to "pr-[PR_NUM]")
 *  -s | --suggest           suggest a custom local branch name based on the PR title
 *
 * Examples:
 *  git pr 1337
 *  git pr --nocommit 1337
 *  git pr -b "feature-fix" 1337
 *  git pr -s 1337
 *  git pr -l
 *  git pr -c
 *
 * For more details, please see the readme file or https://github.com/ozh/git-pr
 */

class Ozh_Git_PR{

    // Version of this class
    public string $version = '1.3';

    // Current repo owner
    public string $owner;

    // Current repo name
    public string $repo;

    // Base branch for the PR (often 'master', but you can also make a PR against another branch)
    public string $base_branch;

    // Pull Request ID to pull
    public int $pr;

    // Do we want to commit changes or not
    public bool $commit;

    // Remote clone URL of the PR
    public string $remote_url;

    // Remote branch of the PR
    private string $remote_branch;

    // Local branch name of the PR
    public string $branch;

    /**
     * Init and do everything
     *
     */
    function __construct() {
        $this->get_owner_and_repo();
        $this->get_cli_params();
        $this->get_pr_repo();
        $this->pull_and_maybe_commit();
    }

    /**
     *  Get the content of this file comment header and send it to print help
     *
     * @return string
     */
    function get_help_msg(): string {
        $lines      = file(__FILE__);
        $in_comment = false;
        $help       = '';
        
        // Read lines of this very file to get the comment header
        foreach($lines as $line) {
            // when we first encounter a line starting with '/**', this means we're now in the header
            if ( !$in_comment && preg_match('|^\s*/\*\*|', $line) ) {
                $in_comment = true;
                continue;
            }
            
            // if we're in the header and encounter a line with '*/', stop reading
            if ( $in_comment && preg_match('|\*/|', $line) ) {
                unset($lines);
                break;
            }
            
            if ( $in_comment ) {
                $help .= preg_replace('/^\s*\*\s/', '', $line);
            }
        }
            
        return $help;
    }
    
    /**
     *  Get parameters from the command line
     */
    function get_cli_params(): void {
        // read arguments from the command line
        $args      = getopt("lnchsb:v", ['suggest','list','nocommit','no-commit','help','branch:','version', 'cleanup'], $option_index);
        $nocommit  = isset($args['n']) || isset($args['nocommit']) || isset($args['no-commit']);
        $help      = isset($args['h']) || isset($args['help']);
        $branch    = ( isset($args['b']) || isset($args['branch']) ) ? ($args['b'] ?? $args['branch']) : false;
        $version   = isset($args['v']) || isset($args['version']);
        $list      = isset($args['l']) || isset($args['list']);
        $suggest   = isset($args['s']) || isset($args['suggest']);
        $cleanup   = isset($args['c']) || isset($args['cleanup']);

        // Mark --branch and --suggest as mutually exclusive
        if ( $branch && $suggest ) {
            $this->display_msg_and_die( "Options --branch and --suggest are mutually exclusive." );
        }

        // PR number must be the only argument after any option and must be numeric
        $pr = array_slice($_SERVER['argv'], $option_index);
        if ( count($pr) != 1 || !ctype_digit($pr[0]) ) {
            $pr = false;
        } else {
            $this->pr = (int)$pr[0];
        }

        if ($cleanup) {
            $this->cleanup_old_pr_remotes();
            exit(0);
        }

        if ($list) {
            $this->display_msg_and_die( $this->list_prs(), exit_code: 0 );
        }

        if ($version) {
            $this->display_msg_and_die( 'git_pr version ' . $this->version, exit_code: 0 );
        }

        if ($help or !$pr) {
            $this->display_msg_and_die( $this->get_help_msg(), exit_code: 0 );
        }

        if ($branch) {
            $this->branch = trim($branch);
        }
        elseif ($suggest) {
            $this->branch = $this->get_suggested_branch_name();
        } else {
            $this->branch = '';
        }
        $this->commit = !$nocommit;
    }
    
    /**
     * Simple curl wrapper
     *
     * Note: SSL verification turned off on purpose
     *
     * @param string $url URL to fetch
     * @return string|void  content of the URL, or dies if an error occurred
     */
    function get_url( $url ) {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER , 1 );
        curl_setopt( $ch, CURLOPT_USERAGENT,'PHP Ozh_Git_PR ' . $this->version );
        $result = curl_exec( $ch );
        curl_close( $ch );
        if ( $result === false ) {
            $this->display_msg_and_die( "Could not read info from $url" );
        }
        return $result;
    }
    
    /**
     * Gets the owner and repo name of the current repo we're in
     *
     * This gets the first fetch repo URL named "origin"
     *
     */
    function get_owner_and_repo( ): void {
        $remote = $this->exec_and_maybe_continue( "git remote get-url origin", false );
        // will get something like: array('https://github.com/YOURLS/YOURLS.git')
        preg_match( "/github.com\/(.*)\/(.*)/", $remote[0], $matches );
        if ( count($matches) == 3 ) {
            $this->owner = $matches[1];
            $this->repo  = trim($matches[2], '.git');
        } else {
            $this->display_msg_and_die( "Could not find Github owner and repo" );
        }
    }

    /**
     * Fetch pull requests from GitHub API
     *
     * @param string $state State filter: 'open', 'closed', 'all' (default: 'open')
     * @param int $per_page Number of results per page (default: 100)
     * @return array|null Array of PRs or null on error
     */
    function fetch_prs( string $state = 'open', int $per_page = 100 ): ?array {
        $url = sprintf(
            "https://api.github.com/repos/%s/%s/pulls?state=%s&per_page=%d",
            $this->owner,
            $this->repo,
            $state,
            $per_page
        );
        
        $json = $this->get_url($url);
        $prs = json_decode($json, true);
        
        return is_array($prs) ? $prs : null;
    }

    /**
     * Lists the pull requests of the current repo
     *
     */
    function list_prs( ): string {
        $prs = $this->fetch_prs('open');
        
        if ($prs === null) {
            return "Error: Could not fetch PR list from GitHub API";
        }
        
        if (empty($prs)) {
            return "No pull requests found";
        }
        
        $list = '';
        foreach( $prs as $pr ) {
            if ( isset( $pr['number'] ) && isset( $pr['title'] ) ) {
                $list .= sprintf(
                    "%d - %s (%s:%s)\n",
                    $pr['number'],
                    $pr['title'],
                    $pr['head']['repo']['full_name'],
                    $pr['head']['ref']
                );
            }
        }
        
        return $list;
    }

    /**
     * Cleanup old PR remotes that are no longer open
     *
     */
    function cleanup_old_pr_remotes(): void {
        // Get all remotes starting with "pr-"
        $remotes = $this->exec_and_maybe_continue("git remote", false);
        $pr_remotes = array_filter($remotes, fn($r) => str_starts_with($r, 'pr-'));
        
        if (empty($pr_remotes)) {
            echo "No PR remotes found to clean up.\n";
            return;
        }
        
        // Fetch all OPEN PRs in a single API call
        $open_prs = $this->fetch_prs('open', 100);
        
        if ($open_prs === null) {
            echo "Error: Could not fetch PR list from GitHub API\n";
            return;
        }
        
        // Build a set of open PR numbers for quick lookup
        $open_pr_numbers = array_map(fn($pr) => (int)$pr['number'], $open_prs);
        
        $removed_count = 0;
        foreach($pr_remotes as $remote) {
            // Extract PR number from remote name (e.g., "pr-4025" -> 4025)
            if (preg_match('/^pr-(\d+)$/', $remote, $matches)) {
                $pr_num = (int)$matches[1];
                
                // Remove remote if PR is not in the open PRs list
                if (!in_array($pr_num, $open_pr_numbers)) {
                    echo sprintf("Removing remote '%s' (PR #%d is not open)\n", $remote, $pr_num);
                    $this->exec_and_maybe_continue("git remote remove $remote");
                    $removed_count++;
                } else {
                    echo sprintf("Keeping remote '%s' (PR #%d is still open)\n", $remote, $pr_num);
                }
            }
        }
        
        echo sprintf("\n%d remote(s) removed.\n", $removed_count);
    }

    /**
     * Get suggested branch name from PR title
     */
    function get_suggested_branch_name( ): string {
        $url = sprintf( "https://api.github.com/repos/%s/%s/pulls/%d", $this->owner, $this->repo, $this->pr );
        $json = $this->get_url( $url );
        $pr = json_decode( $json );
        if ( isset( $pr->title ) ) {
            $sugg = $this->remove_stop_words( $pr->title);
        } else {
            $sugg = 'no-title-found';
        }
        echo "Suggested branch name: $sugg\n";
        echo "Press enter to use this name, or type a new one: ";
        $fin = fopen ("php://stdin","r");
        $line = trim(fgets($fin));
        fclose($fin);
        if ( $line !== '' ) {
            $sugg = $line;
        }

        return $sugg;
    }
    
    /**
     * Gets the URL and branch of the PR repo
     *
     */
    function get_pr_repo( ): void {
        $url = sprintf( "https://api.github.com/repos/%s/%s/pulls/%d", $this->owner, $this->repo, $this->pr );
                
        $json = $this->get_url( $url );
        $json = json_decode( $json );
        
        if ( !isset( $json->head->repo->clone_url ) or !isset( $json->head->ref ) ) {
            $this->display_msg_and_die( sprintf( "Could not find info for PR #%s (maybe PR repo was deleted?)\n", $this->pr ) );
        }
        
        $this->remote_url    = $json->head->repo->clone_url;
        $this->remote_branch = $json->head->ref;
        $this->base_branch   = $json->base->ref;
    }

    /**
     * Creates the new branch and git pull the PR without committing
     *
     */
    function pull_and_maybe_commit( ): void {
        // git remote add pr-1337 https://github.com/SOMEDUDE/SOMEFORK.git (or update if exists)
        // git checkout -b pr-1337 some_branch
        // git pull (--no-commit) --set-upstream pr-1337 SOMEBRANCH
        
        $commit = $this->commit === true ? '' : '--no-commit';
        $branch = $this->branch ?: sprintf("pr-%d", $this->pr);
        $remote_name = sprintf("pr-%d", $this->pr);
        
        // Check if remote already exists
        $existing_remotes = $this->exec_and_maybe_continue( "git remote", false );
        if ( in_array( $remote_name, $existing_remotes ) ) {
            // Remote exists, update its URL in case it changed
            $this->exec_and_maybe_continue( sprintf( "git remote set-url %s %s", $remote_name, $this->remote_url ) );
        } else {
            // Add the PR fork as a new remote
            $this->exec_and_maybe_continue( sprintf( "git remote add %s %s", $remote_name, $this->remote_url ) );
        }
        
        // Create and checkout the new branch
        $this->exec_and_maybe_continue( sprintf( "git checkout -b %s %s", $branch, $this->base_branch ) );
        
        // Pull from the remote and set upstream tracking
        $this->exec_and_maybe_continue( sprintf( "git pull %s --set-upstream %s %s", $commit, $remote_name, $this->remote_branch ) );
    }

    /**
     * Displays message and dies
     *
     * @param string $msg       error message
     * @param int    $exit_code optional exit code (defaults to 1 = error)
     */
    function display_msg_and_die(string $msg, int $exit_code = 1 ): void {
        echo trim($msg) . "\n";
        echo "\n";
        exit($exit_code);
    }

    /**
     * Execs a command and dies if an error occurred
     *
     * @param string $cmd     command to execute
     * @param bool   $display if the command output should be displayed or not
     * @return array $array  command output
     */
    function exec_and_maybe_continue(string $cmd, bool $display = true ): array {
        exec( escapeshellcmd( $cmd ) . " 2>&1", $output );
        
        if ( $display ) {
            echo implode( "\n", $output ) . "\n";
        }
        
        if ( preg_grep( '/fatal|error/', $output ) ) {
            die( "\nScript aborted !\n" );
        }
        
        return $output;
    }

    /**
     * Remove common English stop words from an array of words
     *
     * Code from https://github.com/rap2hpoutre/remove-stop-words/
     *
     * @param string $words list of words
     * @return string Filtered string
     */
    function remove_stop_words($words): string {
        $stop_words = [
            'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves',
            'you', 'your', 'yours', 'yourself', 'yourselves', 'he', 'him', 'his', 'himself',
            'she', 'her', 'hers', 'herself', 'it', 'its', 'itself', 'they', 'them',
            'their', 'theirs', 'themselves', 'what', 'which', 'who', 'whom', 'this', 'that',
            'these', 'those', 'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'having', 'do', 'does', 'did', 'doing', 'a', 'an', 'the',
            'and', 'but', 'if', 'or', 'because', 'as', 'until', 'while', 'of', 'at', 'by', 'for',
            'with', 'about', 'against', 'between', 'into', 'through', 'during', 'before', 'after',
            'above', 'below', 'to', 'from', 'up', 'down', 'in', 'out', 'on', 'off', 'over', 'under',
            'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how',
            'all', 'any', 'both', 'each', 'few', 'more', 'most', 'other', 'some', 'such',
            'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very',
            "'s", "'t", 'can', 'will', 'just', 'don', 'should', 'now',
        ];
        foreach ($stop_words as &$word) {
            $word = '/\b' . preg_quote($word, '/') . '\b/iu';
        }
        $words = preg_replace($stop_words, '', strtolower($words));
        $words = preg_replace('/\s+/', '-', trim($words));
        return $words;
    }
    
}
// Launch and execute everything
new Ozh_Git_PR;
