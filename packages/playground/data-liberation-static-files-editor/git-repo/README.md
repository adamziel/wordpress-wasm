## Git API for WordPress

This is a simple git server that allows you to push and pull your content to a WordPress site as if it was a git repository. Because now it is.

Basic usage with the dirty dev scripts shipped in this PR:

Start WordPress server with this plugin enabled:

```
cd packages/playground/data-liberation-static-files-editor/
bash run.sh
```

Cool! Now you can use it as a git repo:

```
cd my-git-repo-dir
git init
git remote add wp http://localhost:9400/wp-content/plugins/z-data-liberation-static-files-editor/git-repo/index.php\?
git pull wp main
```

All your pages and posts should now be available for editing in the local directory and versioned in your local git repo (and the one maintained by the plugin).

Pushing changes is not supported yet, but it wouldn't be too difficult to implement.
