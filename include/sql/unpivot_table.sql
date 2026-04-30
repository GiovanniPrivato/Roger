IF NOT EXISTS (SELECT * FROM sys.objects WHERE type = 'P' AND OBJECT_ID = OBJECT_ID('[%s].[%s]'))
exec('CREATE PROCEDURE [%s].[%s] AS BEGIN SET NOCOUNT ON; END')	
GO

/****** Object:  StoredProcedure [dbo].[unpivotTable]    Script Date: 23/04/2026 21:42:42 ******/
SET ANSI_NULLS ON
GO

SET QUOTED_IDENTIFIER ON
GO

-- =============================================
-- Author:		<Author,,Name>
-- Create date: <Create Date,,>
-- Description:	<Description,,>
-- =============================================
ALTER PROCEDURE [%s].[%s]
	@table varchar(255),
	@unpivotFieldLike varchar(255),
	@unpivotFieldName varchar(255),
	@additionalFields varchar(max),
	@consolidateToTable bit
AS
BEGIN
	SET NOCOUNT ON;

	IF (EXISTS (SELECT * 
                 FROM INFORMATION_SCHEMA.TABLES 
                 WHERE TABLE_SCHEMA = 'dbo' 
                 AND  TABLE_NAME = @table))
		BEGIN

			DECLARE @field varchar(255),
					@unpivot varchar(max)

			SET @unpivot = ''
			
			DECLARE cur cursor For

			SELECT COLUMN_NAME 
			FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = 'dbo' and TABLE_NAME = @table and COLUMN_NAME like @unpivotFieldLike

			OPEN cur 
			Fetch Next From cur Into @field

			While @@Fetch_Status = 0 
				BEGIN

				SET @unpivot = @unpivot + ' [' + @field + '],'

				Fetch Next From cur Into @field

				END 

			CLOSE cur
			DEALLOCATE cur

			SET @unpivot = substring(@unpivot, 2, LEN(@unpivot) - 2)

			SET @additionalFields = case when @additionalFields = '' then '' else ', ' + @additionalFields end

			exec('CREATE or ALTER VIEW [v'+ @table +'] as SELECT *, REPLACE([' + @unpivotFieldName + '_ORIG], ''' + @unpivotFieldName + ''', '''') as [' + @unpivotFieldName + '] ' + @additionalFields + ' FROM (SELECT * FROM [' + @table + ']) p UNPIVOT ([AMOUNT] FOR [' + @unpivotFieldName + '_ORIG] IN (' + @unpivot + ')) unp')

			IF @consolidateToTable > 0
				BEGIN
					IF (EXISTS (SELECT * 
					FROM INFORMATION_SCHEMA.TABLES 
					WHERE TABLE_SCHEMA = 'dbo' 
					AND  TABLE_NAME = 'unpvt'+ @table)) exec('DROP TABLE [unpvt'+ @table + ']')
					exec('SELECT * into [unpvt'+ @table +'] FROM [v'+ @table +']')
					exec('DROP VIEW [v'+ @table +']')
				END
		END
	ELSE
		BEGIN
			print 'Table does not exist'
		END
END
GO


