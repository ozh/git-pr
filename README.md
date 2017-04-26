# git pr 1337

Simple script to locally pull a PR from Github without committing it, so you can easily identify and review/amend modified files

## Use as a git alias:

Add this to your git config (eg `~/.gitconfig`)

```ini
[alias]
    pr = "!php /full/path/to/ozh_git_pr.php"
```

## Usage

Assuming you want to locally test PR #1337, just do:

```sh
git pr 1337
```

This will be equivalent to :

```sh
git checkout -b pr-1337
git pull --no-commit https://github.com/SOMEDUDE/SOMEFORK.git SOMEBRANCH
```

In details:
* creates a new branch named `pr-1337` and switch to it
* pulls whatever repo/branch has been submitted as the PR on Github
* but does not commit changes, so you still see what files are modified and what's changed in them

Results:

![capture1](https://cloud.githubusercontent.com/assets/223647/25444201/93d3d19a-2aaa-11e7-9df9-456bc22f2ea8.PNG)

![capture2](https://cloud.githubusercontent.com/assets/223647/25444316/f3379ad6-2aaa-11e7-8f71-2814f094b6a5.png)

From now on you can review, of course modify, and then commit changes, or simply `git merge --abort` if proposed changes are not suitable.

## License

Do whatever the hell you want to do with it




