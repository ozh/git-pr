# git pr
> A neat `git` script written in PHP to easily locally pull and test PRs from Github

## Usage

In a nutshell, to locally test PR #1337, just do:
```sh
git pr 1337
```

This will:
* Create a new branch named `pr-1337` and switch to it
* Pull whatever repo/branch has been submitted as the PR on Github
* Set up upstream tracking so you can easily pull updates with `git pull`

This will be equivalent to:
```sh
git remote add pr-1337 https://github.com/SOMEDUDE/SOMEFORK.git
git checkout -b pr-1337 master
git pull --set-upstream pr-1337 SOMEBRANCH
```

### Updating a PR

Once you've pulled a PR, if the contributor updates their code, you can easily get the latest changes:
```sh
git checkout pr-1337
git pull
```

## Options

`git pr` comes with handy options. `git pr -h` prints the following help:

```
Usage: git pr [OPTION] [PR_NUM]

Startup:
  -l | --list              list all pull requests of the current repo
  -c | --cleanup           cleanup remotes of PRs that are no longer open
  -v | --version           display the version number
  -h | --help              display the help message

Pull options:
  -n | --nocommit          pull given PR but don't commit changes

Branch options:
  -b | --branch <branch>   custom local PR branch name (defaults to "pr-[PR_NUM]")
  -s | --suggest           suggest a custom local branch name based on the PR title

Examples:
  git pr 1337
  git pr --nocommit 1337
  git pr -b "feature-fix" 1337
  git pr -s 1337
  git pr -l
  git pr -c
```

## Naming local pull branches

By default, the local branch you're pulling will be named `pr-<NUMBER>`, using the Github PR number.

Quick and handy, but when you're working with `pr-4102`, `pr-4107` and `fix-issue-1337`, it becomes cumbersome to remember which is which.

To avoid this, you can manually specify a branch name with `-b` or `--branch`:
```sh
git pr -b fix/some-issue 1337
```

Even lazier, let the script suggest a local branch name, using a short version of the actual PR title. Say PR #1337 has the title `Add a SQL index for faster lookups !`, using the `-s` or `--suggest` option:
```sh
$ git pr -s 1337
Suggested branch name: add-sql-index-faster-lookups
Press enter to use this name, or type a new one: 
```

## Listing pull requests

To see all open pull requests in the current repository:
```sh
git pr -l
```

This will display:
```
4025 - Fix typo in documentation (somenicedude/YOURLS:fix-typo)
4024 - Add new feature (johndoe/YOURLS:feature-branch)
```

## Cleaning up old remotes

Over time, you'll accumulate remotes for PRs you've tested. To automatically remove remotes for PRs that are no longer open (merged or closed):
```sh
git pr -c
```

This will:
* Check all remotes named `pr-*`
* Query the GitHub API to see which PRs are still open
* Remove remotes for PRs that have been merged or closed
* Keep remotes for PRs that are still open

Example output:
```
Removing remote 'pr-4025' (PR #4025 is not open)
Keeping remote 'pr-4100' (PR #4100 is still open)

1 remote(s) removed.
```

## Why a `-n` switch? Why would I want to *not* commit changes?

With a gigantic pull request that changes a lot of files, it can be easier to review changes, since modified files are marked (if using a tool with icon overlays, like TortoiseGit).

![capture2](https://cloud.githubusercontent.com/assets/223647/25444316/f3379ad6-2aaa-11e7-8f71-2814f094b6a5.png)

From now on you can review, modify if needed, and then commit changes, or simply `git merge --abort` if proposed changes are not suitable.

## Install as a git alias

Add this to your git config (eg `~/.gitconfig`):
```ini
[alias]
    pr = "!php /full/path/to/ozh_git_pr.php"
```

## Requirements

* PHP 7.4 or higher
* Git
* curl extension enabled in PHP

## License

Do whatever the hell you want to do with it
