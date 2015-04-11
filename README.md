# git pr 1337

Simple script to locally pull a PR from Github without committing it

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

## License

Do whatever the hell you want to do with it




