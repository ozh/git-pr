# git pr

Simple script for `git`, to locally pull a PR from Github, with an option to _not_ commit changes

## Install as a git alias:

Add this to your git config (eg `~/.gitconfig`)

```ini
[alias]
    pr = "!php /full/path/to/ozh_git_pr.php"
```

## Usage

Assuming you want to locally test PR #1337, just do:

```sh
git pr 1337
# or
git pr -n 1337
```

In detail, this:

* creates a new branch named `pr-1337` and switch to it
* pulls whatever repo/branch has been submitted as the PR on Github
* with the `-n` option, does not commit changes, so you still see what files are modified and what's changed in them

This will be equivalent to :

```sh
git checkout -b pr-1337
git pull [--no-commit] https://github.com/SOMEDUDE/SOMEFORK.git SOMEBRANCH
```

#### Why a `-n` switch ? Why would I want to *not* commit changes ?

With a gigantic pull request that changes a lot of files, it can be easier to review changes, since modified files are marked (if using a tool with icon overlays, like TortoiseGit)

![capture2](https://cloud.githubusercontent.com/assets/223647/25444316/f3379ad6-2aaa-11e7-8f71-2814f094b6a5.png)

From now on you can review, of course modify, and then commit changes, or simply `git merge --abort` if proposed changes are not suitable.


## License

Do whatever the hell you want to do with it
