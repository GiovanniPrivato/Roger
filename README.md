# Roger
Roger is a ready-to-use package that makes csv or SAP data flowing to SQL with no effort!

Two modules are included (you can of course use both):

csv2SQL: Extract any csv file with TAB delimiter and find it loaded on SQL in a table having the name of your file.
SAP2SQL: Create any SAP protocol on Board Connector and load its data to SQL with just one Board flag.
(JSON2SQL: coming soon)

# INSTALLATION
INSTALLATION REQUIRES 3 SIMPLE STEPS:

    1. Unzip the folder and place it in any given PATH.
    2. Edit the config/config.php file with notepad changing parameters according to the server environment. You can create multiple config files for each SQL database or Board project, so to use\write different files\SAP Protocols\Folders\SQL Databases on the same server.
    3.Copy the following jobs under Board\Jobs folder.

    IF YOU NEED csv2SQL module: copy the job csv2SQL.bat, and change paths according to the PATH chosen in 1 and the [config] as the name of the config file edit in 2. If you have multiple config files, you can create multiple csv2SQL.bat files changing config parameter.
    IF YOU NEED SAP2SQL module: copy the jobs SAP2SQL.bat and refreshSAPProtocols.bat, and change paths according to the PATH chosed in 1 and the [config] as the name of the config file edit in 2.
        refreshSAPProtocols creates in PATH a txt to be read by Board with all names of SAP protocols. If you have multiple config files, you can create multiple refreshSAPProtocols.bat files changing config parameter.
        SAP2SQL requires an extraction from Board in the PATH, named Protocols_to_upload.txt with TAB delimiter, with the list of Protocols to be uploaded to SQL. If you have multiple config files, you can create multiple SAP2SQL.bat files changing config parameter.

# Usage example
## csv2SQL

    1. Put any csv file (directly from Board or manually) in the PATH\csv folder specified in config.
    2. Such files MUST have csv extensions and have a header.
    3. Run csv2SQL.bat to load such file(s) to SQL. Roger will create a table (dropping an already existing one) having the same name of the file, renaming with progressive number any similar field name.
    4. You’ll find processed files in the “PATH\csv_processed” specified in config folder once done.

## SAP2SQL

    1. Run the refreshSAPProtocols.bat job from Board to refresh the file specified in config with SAP protocols list.
    2. Read the file with Board in an entity called SAP Protocols.
    3. Prepare a flag cube to check off the protocols to be loaded up to SQL.
    4. Extract the cube in the specified PATH in the config file.
    5. Run SAP2SQL. Roger will create a table (dropping an already existing one) having the same name of the SAP protocol, renaming with progressive number any similar field name.

