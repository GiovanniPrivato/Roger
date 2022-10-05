--IF NOT EXISTS (SELECT * FROM sys.objects WHERE type = 'P' AND OBJECT_ID = OBJECT_ID('dbo.roger_auto_consolidate_table'))
--exec('CREATE PROCEDURE [dbo].[roger_auto_consolidate_table] AS BEGIN SET NOCOUNT ON; END')	
ALTER PROCEDURE [dbo].[%s]
@table_name varchar(255),
@final_table varchar(255),
@field_1 varchar(255),
@field_2 varchar(255),
@field_3 varchar(255),
@field_4 varchar(255),
@field_5 varchar(255)
AS
BEGIN
DECLARE @table_cursor Cursor,
		@c_table varchar(255),
		@c_field_1 varchar(255),
		@c_field_2 varchar(255),
		@c_field_3 varchar(255),
		@c_field_4 varchar(255),
		@c_field_5 varchar(255),
		@c_table_cursor Cursor,
		@c_c_table varchar(255),
		@c_c_field_1 varchar(255),
		@c_c_field_2 varchar(255),
		@c_c_field_3 varchar(255),
		@c_c_field_4 varchar(255),
		@c_c_field_5 varchar(255)

	SET @table_cursor = Cursor For
	SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
	WHERE TABLE_SCHEMA = 'dbo' 
	AND TABLE_TYPE = 'BASE TABLE'
	AND TABLE_NAME LIKE @table_name
	AND TABLE_NAME <> @final_table


	OPEN @table_cursor

	Fetch Next From @table_cursor
	Into @c_table

While (@@FETCH_STATUS = 0)
	BEGIN
		
			DECLARE @metaTable TABLE(FIELD_1 varchar(255), FIELD_2 varchar(255), FIELD_3 VARCHAR(255), FIELD_4 VARCHAR(255), FIELD_5 VARCHAR(255))
			
			IF (NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES 
			WHERE TABLE_SCHEMA = 'dbo' 
			AND  TABLE_NAME = @final_table)) 
			BEGIN
				exec('SELECT * INTO ' + @final_table + ' from ' + @c_table)
			END 
			ELSE 
			BEGIN
				INSERT INTO @metaTable(FIELD_1, FIELD_2, FIELD_3, FIELD_4, FIELD_5)
				exec('SELECT distinct ' + @field_1 + ',  ' + @field_2 + ', ' + @field_3 + ', ' + @field_4 + ', ' + @field_5 +' FROM ' + @c_table)

			
				SET @c_table_cursor = Cursor For
				SELECT * FROM @metaTable

				OPEN @c_table_cursor

				Fetch Next From @c_table_cursor
				Into @c_field_1, @c_field_2, @c_field_3, @c_field_4, @c_field_5
				While (@@FETCH_STATUS = 0)
					BEGIN
					exec('DELETE FROM ' + @final_table + ' WHERE ' + @field_1 + '= '''+ @c_field_1 + ''' AND ' + @field_2 + '='''+ @c_field_2 + ''' AND ' + @field_3 + '='''+ @c_field_3 + ''' AND ' + @field_4 + '='''+ @c_field_4 + ''' AND ' + @field_5 + '='''+ @c_field_5 + '''')

					exec('INSERT INTO ' + @final_table + ' SELECT * FROM ' + @c_table + ' WHERE ' + @field_1 + '= '''+ @c_field_1 + ''' AND ' + @field_2 + '='''+ @c_field_2 + ''' AND ' + @field_3 + '='''+ @c_field_3 + ''' AND ' + @field_4 + '='''+ @c_field_4 + ''' AND ' + @field_5 + '='''+ @c_field_5 + '''')

					Fetch Next From @c_table_cursor Into @c_field_1, @c_field_2, @c_field_3, @c_field_4, @c_field_5
					END
				CLOSE @c_table_cursor 
				DEALLOCATE @c_table_cursor

				delete from @metaTable	
			END
			exec('DROP TABLE ' + @c_table)
		Fetch Next From @table_cursor Into @c_table
	END

	CLOSE @table_cursor 
DEALLOCATE @table_cursor

END
