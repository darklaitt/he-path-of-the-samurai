program LegacyCSV;

{$mode objfpc}{$H+}

uses
  SysUtils, DateUtils, Classes;

{ Global logger }
procedure LogInfo(const msg: string);
begin
  WriteLn('[', FormatDateTime('yyyy-mm-dd hh:nn:ss', Now), '] [INFO] ', msg);
  Flush(Output);
end;

procedure LogError(const msg: string);
begin
  WriteLn(ErrOutput, '[', FormatDateTime('yyyy-mm-dd hh:nn:ss', Now), '] [ERROR] ', msg);
  Flush(ErrOutput);
end;

function GetEnvDef(const name, def: string): string;
var v: string;
begin
  v := GetEnvironmentVariable(name);
  if v = '' then Exit(def) else Exit(v);
end;

function RandFloat(minV, maxV: Double): Double;
begin
  Result := minV + Random * (maxV - minV);
end;

procedure GenerateAndCopy();
var
  outDir, fn, fullpath, pghost, pgport, pguser, pgpass, pgdb, copyCmd, boolText: string;
  f: TextFile;
  ts: string;
  voltage, temp: Double;
  isOk: Boolean;
  exitCode: Integer;
begin
  try
    // Generate timestamp and filename
    outDir := GetEnvDef('CSV_OUT_DIR', '/data/csv');
    ts := FormatDateTime('yyyymmdd_hhnnss', Now);
    fn := 'telemetry_' + ts + '.csv';
    fullpath := IncludeTrailingPathDelimiter(outDir) + fn;

    // Generate telemetry values
    voltage := RandFloat(3.2, 12.6);
    temp := RandFloat(-50.0, 80.0);
    isOk := (Random(2) = 1);
    if isOk then
      boolText := 'ИСТИНА'
    else
      boolText := 'ЛОЖЬ';

    // Write CSV file (строгие типы: timestamp, logical, numeric, text)
    LogInfo('generating CSV: ' + fullpath);
    AssignFile(f, fullpath);
    Rewrite(f);
    try
      // Header:
      // recorded_at (TIMESTAMP), is_ok (LOGICAL TEXT: ИСТИНА/ЛОЖЬ), voltage (NUMERIC), temp (NUMERIC), source_file (TEXT)
      Writeln(f, 'recorded_at,is_ok,voltage,temp,source_file');
      // Timestamp в формате, который корректно парсит PostgreSQL
      Writeln(f, FormatDateTime('yyyy-mm-dd hh:nn:ss', Now) + ',' +
                 boolText + ',' +
                 FormatFloat('0.00', voltage) + ',' +
                 FormatFloat('0.00', temp) + ',' +
                 fn);
      LogInfo('CSV written successfully');
    finally
      CloseFile(f);
    end;

    // Get PostgreSQL connection parameters
    pghost := GetEnvDef('PGHOST', 'db');
    pgport := GetEnvDef('PGPORT', '5432');
    pguser := GetEnvDef('PGUSER', 'monouser');
    pgpass := GetEnvDef('PGPASSWORD', 'monopass');
    pgdb   := GetEnvDef('PGDATABASE', 'monolith');

    // COPY into PostgreSQL
    LogInfo('inserting into telemetry_legacy via COPY');
    copyCmd := 'PGPASSWORD=' + pgpass + ' psql -h ' + pghost + ' -p ' + pgport + 
               ' -U ' + pguser + ' -d ' + pgdb +
               ' -c "\copy telemetry_legacy(recorded_at, is_ok, voltage, temp, source_file) ' +
               'FROM ''' + fullpath + ''' WITH (FORMAT csv, HEADER true)"';
    
    // Execute COPY command via system shell
    exitCode := ExecuteProcess('/bin/sh', '-c ' + copyCmd);
    
    if exitCode <> 0 then
      LogError('COPY command failed with exit code: ' + IntToStr(exitCode))
    else
      LogInfo('COPY completed successfully (voltage=' + FormatFloat('0.00', voltage) +
              ', temp=' + FormatFloat('0.00', temp) + ')');

  except
    on E: Exception do
      LogError('GenerateAndCopy exception: ' + E.Message);
  end;
end;

var 
  period: Integer;
  iterations: Integer;
begin
  Randomize;
  
  { Get configuration from environment }
  period := StrToIntDef(GetEnvDef('GEN_PERIOD_SEC', '300'), 300);
  LogInfo('Starting CSV generator daemon');
  LogInfo('Generation period: ' + IntToStr(period) + ' seconds');
  LogInfo('CSV output directory: ' + GetEnvDef('CSV_OUT_DIR', '/data/csv'));
  
  iterations := 0;
  
  { Main loop }
  while True do
  begin
    Inc(iterations);
    LogInfo('--- Iteration ' + IntToStr(iterations) + ' ---');
    
    try
      GenerateAndCopy();
    except
      on E: Exception do
        LogError('Main loop exception: ' + E.Message);
    end;
    
    LogInfo('sleeping for ' + IntToStr(period) + ' seconds...');
    Sleep(period * 1000);
  end;
end.
