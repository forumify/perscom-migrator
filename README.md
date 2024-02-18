# PERSCOM Invision to Cloud Migrator

Created predominantly for use with [forumify](https://forumify.net) in combination with the [forumify PERSCOM plugin](https://github.com/forumify/forumify-perscom-plugin).

Migrate your old PERSCOM data to PERSCOM.io. This migrator is not a end-all be-all solution.
Please double-check all users and keep a backup of your old PERSCOM data in case anything went missing during the migration.

### Usage

1. Download the latest version in [releases](https://github.com/forumify/perscom-migrator/releases),
2. Upload & install the application through your Invision Community AdminCP,
3. A new AdminCP menu entry has been added, navigate to settings and configure your PERSCOM.io credentials,
4. Select "Migrate Data", carefully read the instructions, and press migrate,
5. Sit back with a cup of coffee, this may take a while depending on the amount of data.

If you run into any errors, please create an [issue here](https://github.com/forumify/perscom-migrator/issues).

*Make sure you include the output or any errors in your issue! Even though this migrator will not delete anything, it is always good practice to have a backup!*

### Development

If you think you can fix your issue, or have a suggestion for improvements, we are accepting contributions so feel free to create a merge request so the entire PERSCOM community can benefit.

To develop on this application:

1. Clone this repository somewhere,
2. Download Invision Community and drop all the files into this repository (keep files that are going to be overwritten),
3. Download the Invision Community SDK and drop all files into this repository,
4. Run Invision Community and do first time setup (recommend to run using docker, docker-compose.yml provided in this repo),
5. Download the PERSCOM application and install using AdminCP.

[This application is licensed under the MIT license](/LICENSE.md). We are not liable for any lost data, nor do we provide any warranty.
