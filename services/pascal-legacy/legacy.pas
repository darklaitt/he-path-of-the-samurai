program LegacyCSV;

{$mode objfpc}{$H+}

uses
  SysUtils, DateUtils, Process, Classes;

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
  outDir, fn, fullpath, pghost, pgport, pguser, pgpass, pgdb, copyCmd: string;
  f: TextFile;
  ts: string;
  voltage, temp: Double;
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

    // Write CSV file
    LogInfo('generating CSV: ' + fullpath);
    AssignFile(f, fullpath);
    Rewrite(f);
    try
      Writeln(f, 'recorded_at,voltage,temp,source_file');
      Writeln(f, FormatDateTime('yyyy-mm-dd hh:nn:ss', Now) + ',' +
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
    copyCmd := 'psql -h ' + pghost + ' -p ' + pgport + ' -U ' + pguser + 
               ' -d ' + pgdb +
               ' -c "\copy telemetry_legacy(recorded_at, voltage, temp, source_file) ' +
               'FROM ''' + fullpath + ''' WITH (FORMAT csv, HEADER true)"';
    
    // Set password in environment for psql
    SetEnvironmentVariable('PGPASSWORD', pgpass);
    
    // Execute COPY command
    exitCode := fpSystem(copyCmd);
    
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
