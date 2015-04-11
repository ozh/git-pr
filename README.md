# git pr 1337

Simple script to locally pull a PR from Github without committing it

## Use as a git alias:

Add this to your git config (eg `~/.gitconfig`)

```ini
[alias]
    pr = "!f() { git fetch -fu ${2:-origin} refs/pull/$1/head:pr/$1 && git checkout pr/$1; }; f"
```

## Usage

Assuming you want to locally test PR #1337, just do:

```sh
git pr 1337
```

In details:
* keep things cool :)

## License

Do whatever the hell you want to do with it




