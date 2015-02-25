# M12.DbExport - Neos plugin to import/export database

Does a full or partial (content-only tables) database dump into .sql file. It adds `db:export` and `db:import` commands to `./flow`.

It might be considered as a temporary workaround to sometimes faulty `./flow site:export` and `./flow site:import` commands.

By default it exports only "content" tables listed in [Settings.yaml](Configuration/Settings.yaml), unless you specify `--mode=all`.


## Installation

`composer require m12/neos-plugin-dbexport:dev-master`


## Usage

#### Export

`./flow db:export --package-key Your.SitePackage [--mode=content]`  
to have a .sql dump in `Your.SitePackage/Private/Resources/Content/Content.sql`

`./flow db:export --package-key Your.SitePackage [--mode=all]`  
to have a .sql dump in `Your.SitePackage/Private/Resources/Content/Dump.sql`

You can also specify path where the .sql file will be exported:  
`./flow db:export --sql-file my-dump.sql [--mode=content|all]`  

#### Import

**Caveat**: import process will drop and override the tables, therefore make sure you won't press wrong buttons accidentally.

`./flow db:import --sql-file my-dump.sql`  
to simply import selected .sql file.

`./flow db:import --package-key Your.SitePackage`  
to import `Your.SitePackage/Private/Resources/Content/Content.sql`

`./flow db:import --package-key Your.SitePackage --mode=all`  
to import `Your.SitePackage/Private/Resources/Content/Dump.sql`


## Author(s)

* Marcin Ryzycki marcin@m12.io
