exec getColNames BigComuni
if exists(select 1 from sys.views where name='vTest' and type='v')
drop view vTest;
GO

create VIEW vTest AS
SELECT [ISTAT]
      ,[COMUNE]
      ,[PROVINCIA]
      ,[REGIONE]
      ,[PREFISSO]
	  /*
	  test
	  GO
	  
	  
	  *****/
      ,[CAP]
      ,[CODFISCO]
      ,[ABITANTI]
      ,[LINK]
	  --GO
  FROM [test].[dbo].[BigComuni]