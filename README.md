# git pr
<img width="546" height="165" alt="image" src="https://github.com/user-attachments/assets/1e63b872-e7be-4947-b53f-1a0bac630e78" />

> A neat `git` script written in PHP to easily locally pull a PR from Github

## Usage

In a nutshell, to locally test PR #1337, just do:

```sh
git pr 1337
```

This will

* creates a new branch named `pr-1337` and switch to it
* pulls whatever repo/branch has been submitted as the PR on Github

This will be equivalent to :

```sh
git checkout -b pr-1337
git pull https://github.com/SOMEDUDE/SOMEFORK.git SOMEBRANCH
```

## Options

`git pr` comes with handy options. `git pr -h` prints the following help :

```
Usage: git pr [OPTION] [PR_NUM]

Startup:
  -l | --list              list all pull requests of the current repo
  -v | --version           display the version number
  -h | --help              display the help message

 Pull options:
  -n | --nocommit          pull given PR but do not commit changes
  -t | --test              test the script, do not actually pull the PR

 Branch name options:
  -b | --branch <branch>   custom local PR branch name (defaults to "pr-[PR_NUM]")
  -s | --suggest           suggest a custom local branch name based on the PR title
```

## Naming local pull branches

By default, the local branch you're pulling will be named `pr-<NUMBER>`, using the Github PR number.  
Quick and handy, but when you're working with `pr-4102`, `pr-4107` and `fix-issue-1337`, it becomes
cumbersome to remember which is which.

To avoid this, you can manually specify a branch name with `-b` or `--branch`:
```
git pr -b fix/some-issue 1337
```

Even lazier, let the script suggest a local branch name, using a short version of the actual branch title.  
Say PR #1337 has the title `Add a SQL index for faster lookups !`, using the `-s` or `--suggest` option:

```
$ git pr -s 1337
Suggested branch name: add-sql-index-faster-lookups
Press enter to use this name, or type a new one: 
```

## Why a `-n` switch ? Why would I want to *not* commit changes ?

With a gigantic pull request that changes a lot of files, it can be easier to review changes, since modified files are marked (if using a tool with icon overlays, like TortoiseGit)

![capture2](https://cloud.githubusercontent.com/assets/223647/25444316/f3379ad6-2aaa-11e7-8f71-2814f094b6a5.png)

From now on you can review, of course modify, and then commit changes, or simply `git merge --abort` if proposed changes are not suitable.

## Install as a git alias:

Add this to your git config (eg `~/.gitconfig`)

```ini
[alias]
    pr = "!php /full/path/to/ozh_git_pr.php"
```

## License

Do whatever the hell you want to do with it
