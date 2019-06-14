```
                             _ _
   _ __ ___   ___   ___   __| | | ___
  | '_ ` _ \ / _ \ / _ \ / _` | |/ _ \
  | | | | | | (_) | (_) | (_| | |  __/
  |_| |_| |_|\___/ \___/ \__,_|_|\___|
                      _          _
  __      _____  _ __| | ___ __ | | __ _  ___ ___
  \ \ /\ / / _ \| '__| |/ / '_ \| |/ _` |/ __/ _ \
   \ V  V / (_) | |  |   <| |_) | | (_| | (_|  __/
    \_/\_/ \___/|_|  |_|\_\ .__/|_|\__,_|\___\___|
                          |_|
```
Moodle Workplace is the official Moodle solution for organisational learning
and development. It has been developed in collaboration with key Moodle
Partners from around the world, pulling together best practices and services to
create a consistent and stable platform for organisational learning.

See <https://moodle.com/workplace/> for details of Moodle Workplace features.

## Setup for development

You can clone this repository using command below, which will also initialise
submodule dependencies and clone them in respective directories.

  `git clone --recurse-submodules git@git.in.moodle.com:workplace/workplacedev.git`

Alternatively, if you already checked-out master branch of this repository
(e.g. you added new remote repo to existing Moodle project), all you need is to
initialise submodules using command:

  `git submodule update --init`

This will add all required plugins to your setup (do not worry if you already
have git clones of those plugins in respective directories, it will not affect
command run).

### Upstream changes in workplace repo

If there are upstream changes in master branch (e.g. new release), you need to
pull master branch first and then update working tree of submodules:

  `git pull` `git submodule update`

Notice that submodule update command above brings your submodules inline with
commits recorded in submodule index in workplace master branch. You need to use
it not only for upstream updates, but also if you checkout certain state in
workplace repo history, e.g. version tag or different branch.

### Upstream changes in submodules (plugins)

You can pull upstream changes for all registered submodules (plugins) by
running:

  `git submodule update --remote`

When you are pulling remote changes with `--remote` argument, by default this
does not overwrite your local changes in plugins, it just checks out the latest
mater commits in each plugin leaving it in headless mode.

You can commit changes after running above command to record the current state
of submodules (represented as latest commit hashes for each submodule).

In order to work on the plugin development, just `cd` to plugin directory and
treat it as independent git repo (you can switch branches, push upstream, this
will not affect main workplace repo in any way).
