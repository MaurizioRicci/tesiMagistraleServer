<?php

/* /
 * Tutte le query usate con parametri sono preparate. 
 * Internamente viene usato pg_prepare e pg_send_execute; essi necessitano di uno statementID. Questo
 * viene passatao come parametro alle varie funzioni
 */

$transazione_fallita_msg = 'Impossibile completare la transazione';
/* /
 * Controlla il range di ID (estremi inclusi) che un utente può usare. Vero se l'id è valido, Falso se fuori dal range permesso.
 * Occorre prima controllare l'esistenza dell'utente con risolviUtente(...)
 */

/* /
 * Rende la funzione Postgres che si occupa di creare un timestamp UTC
 */

function timestamp_utc_txt() {
    return "timezone('UTC'::text, CURRENT_TIMESTAMP)";
}

function checkID($conn, $stmtID, $username, $password, $id_to_check) {
    if (isset($username) && isset($password)) {
        $query = "SELECT id_min, id_max FROM public.utenti WHERE username=$1 and password=$2";
        $resp = runPreparedQuery($conn, $stmtID, $query, array($username, $password));
        if ($resp['ok']) {
            $row = pg_fetch_assoc($resp['data']);
            return intval($row['id_min']) <= intval($id_to_check) &&
                    intval($row['id_max']) >= intval($id_to_check);
        }
    }
    return false;
}

/* /
 * valida un utente e ne estrae il ruolo. Restituisce null se non è stato trovato.
 * Altrimenti un dizionario con id&role (ruolo)
 */

function risolviUtente($conn, $stmtID, $username, $password) {
    if (isset($username) && isset($password)) {
        $query = "SELECT gid, role FROM utenti WHERE username=$1 and password=$2";
        $resp = runPreparedQuery($conn, $stmtID, $query, array($username, $password));
        if ($resp['ok'] && pg_num_rows($resp['data']) > 0) {
            $row = pg_fetch_assoc($resp['data']);
            return array(
                'id' => $row['gid'],
                'role' => $row['role']
            );
        }
    }
    return null;
}

function latLngArrToGeomTxt($latLngArr) {
    if (is_null($latLngArr) || count($latLngArr) <= 0)
        return 'NULL';
    $strArr = [];
    $initialPairTxt = join(' ', $latLngArr[0]);
    foreach ($latLngArr as $latLngPair) {
        array_push($strArr, "$latLngPair[0] $latLngPair[1]");
    }
    // l'ultimo elemento deve essere uguale al primo per chiudere il poligono
    array_push($strArr, $initialPairTxt);
    $txt = "MULTIPOLYGON(((" . join(',', $strArr) . ")))";
    $ST_GeomFromText = "ST_GeomFromText('$txt'::text, 4326)";
    return $ST_GeomFromText;
}

function esisteBene($conn, $stmtID, $idBene, $idUtenteBene) {
    $resp = null;
    if (!isset($idUtenteBene)) {
        $resp = runPreparedQuery($conn, $stmtID, "SELECT id from benigeo WHERE id=$1",
                [$idBene]);
    } else {
        $resp = runPreparedQuery($conn, $stmtID, "SELECT id from tmp_db.benigeo WHERE id=$1 and id_utente=$2",
                [$idBene, $idUtenteBene]);
    }
    return $resp['ok'] && pg_num_rows($resp['data']) > 0;
}

function replaceIntoBeniGeo($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $tablename = 'public.benigeo';
    $query = "update $tablename SET ident=$1, descr=$2, mec=$3, meo=$4, bibli=$5," .
            " note=$6, topon=$7, comun=$8, geom=$geomTxt, esist=$9 WHERE id=$10";
    return runPreparedQuery($conn, $stmtID, $query, array(
        $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $esist, $id
    ));
}

function replaceIntoFunzioniGeo($conn, $stmtID, $id, $idbene, $idbener, $denom, $denomr,
        $data, $tipodata, $funzione, $bibl, $note) {
    $tablename = 'funzionigeo';
    $query = "update $tablename SET id_bene=$1, denominazione=$2, data=$3, tipodata=$4,"
            . "funzione=$5, id_bener=$6, denominazioner=$7,"
            . "bibliografia=$8, note=$9 WHERE id=$10";
    return runPreparedQuery($conn, $stmtID, $query,
            [$idbene, $denom, $data, $tipodata, $funzione, $idbener, $denomr, $bibl, $note, $id]);
}

function insertIntoBeniGeo($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $tablename = 'public.benigeo';
    $query = "INSERT INTO $tablename(id, ident, descr, mec, meo, bibli, note, topon, comun, geom, esist) " .
            "VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$geomTxt,$10";
    return runPreparedQuery($conn, $stmtID, $query, array(
        $id, $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $esist
    ));
}

function insertIntoFunzioniGeo($conn, $stmtID, $idbene, $idbener, $denom, $denomr,
        $data, $tipodata, $funzione, $bibl, $note) {
    $tablename = 'funzionigeo';
    $query = "INSERT INTO $tablename(id_bene, denominazione, data, tipodata, funzione, id_bener, denominazioner,"
            . "bibliografia, note) " .
            "VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9) RETURNING id";
    return runPreparedQuery($conn, $stmtID, $query,
            [$idbene, $denom, $data, $tipodata, $funzione, $idbener, $denomr, $bibl, $note]);
}

function insertIntoBeniGeoTmp($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $user_id, $status, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $tablename = 'tmp_db.benigeo';
    $query = "INSERT INTO $tablename(id, ident, descr, mec, meo, bibli, note, topon, comun, geom, id_utente, status, esist) " .
            "VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$geomTxt,$10,$11, $12)";
    return runPreparedQuery($conn, $stmtID, $query,
            [$id, $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $user_id, $status, $esist]);
}

function insertIntoFunzioniGeoTmp($conn, $stmtID, $idbene, $idbener, $denom, $denomr,
        $data, $tipodata, $funzione, $bibl, $note, $id_utente, $id_utente_bene, $id_utente_bener, $status) {
    $tablename = 'tmp_db.funzionigeo';
    $query = "INSERT INTO $tablename(id_bene, denominazione, data, tipodata, funzione, id_bener, denominazioner,"
            . "bibliografia, note, id_utente, id_utente_bene, id_utente_bener, status) " .
            "VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13) RETURNING id";
    return runPreparedQuery($conn, $stmtID, $query,
            [$idbene, $denom, $data, $tipodata, $funzione, $idbener, $denomr, $bibl, $note,
                $id_utente, $id_utente_bene, $id_utente_bener, $status]);
}

function insertFunzioniGeoRuoli($conn, $stmtID, $id_funzione, $ruoloArr, $ruolorArr, $tmp_db) {
    $lastQuery = null;
    $maxLength = max(count($ruoloArr), count($ruolorArr));
    $tablespace = $tmp_db ? 'tmp_db' : 'public';
    $tablename = 'funzionigeo_ruoli';
    for ($c = 0; $c < $maxLength; $c++) {
        $curr_ruolo = isset($ruoloArr[$c]) ? $ruoloArr[$c] : null;
        $curr_ruolor = isset($ruolorArr[$c]) ? $ruolorArr[$c] : null;
        $query = "INSERT INTO $tablespace.$tablename(id_funzione, ruolo, ruolor) VALUES($1, $2, $3)";
        $lastQuery = runPreparedQuery($conn, $stmtID, $query, [$id_funzione, $curr_ruolo, $curr_ruolor]);
        if (!$lastQuery['ok']) {
            break;
        }
    }
    return $lastQuery;
}

function replaceIntoBeniGeoTmp($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $user, $status, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $timestamp_utc_txt = timestamp_utc_txt();
    $tablename = 'tmp_db.benigeo';
    $query = "update $tablename SET ident=$1, descr=$2, mec=$3, meo=$4, bibli=$5," .
            " note=$6, topon=$7, comun=$8, geom=$geomTxt, id_utente=$9, status=$10,"
            . "timestamp_utc_txt = $timestamp_utc_txt, esist=$11 WHERE id=$12 and id_utente=$9";
    return runPreparedQuery($conn, $stmtID, $query, array(
        $ident, $descr, $mec, $meo, $bibl, $note, $topon, $comun, $user, $status, $esist, $id
    ));
}

function replaceIntoFunzioniGeoTmp($conn, $stmtID, $id, $idbene, $idbener, $denom, $denomr,
        $data, $tipodata, $funzione, $bibl, $note, $id_utente, $id_utente_bene, $id_utente_bener, $status) {
    $tablename = 'tmp_db.funzionigeo';
    $timestamp_utc_txt = timestamp_utc_txt();
    $query = "update $tablename SET id_bene=$1, denominazione=$2, data=$3, tipodata=$4,"
            . "funzione=$5, id_bener=$6, denominazioner=$7,"
            . "bibliografia=$8, note=$9, id_utente=$10, status=$11, id_utente_bene=$12,"
            . "id_utente_bener=13, timestamp_utc_txt=$timestamp_utc_txt WHERE id=$14 and id_utente=$10";
    return runPreparedQuery($conn, $stmtID, $query,
            [$idbene, $denom, $data, $tipodata, $funzione, $idbener, $denomr, $bibl, $note,
                $id_utente, $status, $id_utente_bene, $id_utente_bener, $id]);
}

function upsertIntoBeniGeoTmp($conn, $stmtID, $id, $ident, $descr, $mec, $meo, $bibl, $note,
        $topon, $comun, $geom, $user, $status, $esist) {
    $geomTxt = latLngArrToGeomTxt($geom);
    $timestamp_utc_txt = timestamp_utc_txt();
    $query = "INSERT INTO tmp_db.benigeo(id, id_utente, ident, descr, mec, meo, bibli, note, topon, esist, comun, geom, status) 
            VALUES($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $geomTxt, $12)
            ON CONFLICT (id, id_utente) DO UPDATE SET id = $1,
            id_utente = $2,
            ident = $3, descr = $4,
            mec = $5, meo = $6,
            bibli = $7, note = $8,
            topon = $9, esist = $10, timestamp_utc = $timestamp_utc_txt,
            comun = $11, geom = $geomTxt, status = $12";
    return runPreparedQuery($conn, $stmtID, $query, [$id, $user, $ident,
        $descr, $mec, $meo, $bibl, $note, $topon, $esist, $comun, $status]);
}

function upsertIntoFunzioniGeoTmp($conn, $stmtID, $id, $idbene, $idbener, $denom, $denomr,
        $data, $tipodata, $funzione, $bibl, $note, $id_utente, $id_utente_bene, $id_utente_bener, $status) {
    $tablename = 'tmp_db.funzionigeo';
    $query = "INSERT INTO $tablename(id_bene, denominazione, data, tipodata, funzione, id_bener, denominazioner,
            bibliografia, note, id, id_utente, id_utente_bener, id_utente_bener, status)
            VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14)
            ON CONFLICT (id,id_utente) DO UPDATE SET id_bene=$1,
            denominazione=$2, data=$3, tipodata=$4, funzione=$5,
            id_bener=$6, denominazioner=$7, bibliografia=$8, note=$9,
            id=$10, id_utente=$11, id_utente_bene=$12, id_utente_bener=$13, status=$14
            RETURNING id";
    return runPreparedQuery($conn, $stmtID, $query,
            [$idbene, $denom, $data, $tipodata, $funzione, $idbener, $denomr, $bibl, $note, $id,
                $id_utente, $id_utente_bene, $id_utente_bener, $status]);
}

function insertIntoManipolaBene($conn, $stmtID, $userID, $beneID) {
    $query = "INSERT INTO public.manipola_bene(id_utente, id_bene) " .
            "VALUES($1,$2)";
    return runPreparedQuery($conn, $stmtID, $query, array($userID, $beneID));
}

function insertIntoManipolaFunzione($conn, $stmtID, $userID, $funzioneID) {
    $query = "INSERT INTO public.manipola_funzione(id_utente, id_funzione) " .
            "VALUES($1,$2)";
    return runPreparedQuery($conn, $stmtID, $query, array($userID, $funzioneID));
}

/* /
 * Copia un bene temporaneo nell'archivio definitivo. Notare che se il bene nell'archivio
 * definitivo esiste già, questo verrà rimpiazzato dal bene nell'archivio temporaneo.
 */

function upsertBeneTmpToBeniGeo($conn, $stmtID, $id, $id_utente) {
    $query = "WITH tmp_bene AS (
                SELECT * from tmp_db.benigeo WHERE id=$1 and id_utente=$2
            )
            INSERT INTO public.benigeo(id, ident, descr, mec, meo, bibli, note, topon, esist, comun, geom) 
            SELECT id, ident, descr, mec, meo, bibli, note, topon, esist, comun, geom FROM tmp_bene
            ON CONFLICT (id) DO UPDATE SET id = (SELECT id FROM tmp_bene),
            ident = (SELECT ident FROM tmp_bene), descr = (SELECT descr FROM tmp_bene),
            mec = (SELECT mec FROM tmp_bene), meo = (SELECT meo FROM tmp_bene),
            bibli = (SELECT bibli FROM tmp_bene), note = (SELECT note FROM tmp_bene),
            topon = (SELECT topon FROM tmp_bene), esist = (SELECT esist FROM tmp_bene),
            comun = (SELECT comun FROM tmp_bene), geom = (SELECT geom FROM tmp_bene)";
    return runPreparedQuery($conn, $stmtID, $query, [$id, $id_utente]);
}

/* /
 * Copia una funzione temporanea nell'archivio definitivo.
 */

function upsertFunzioneTmpToBeniGeo($conn, $stmtID, $id, $id_utente) {
    $query = "WITH tmp_funzione AS (
                SELECT * from tmp_db.funzionigeo WHERE id=$1 and id_utente=$2
            )
            INSERT INTO public.funzionigeo(id_bene, lotto, denominazione, data, tipodata, funzione, id_bener, denominazioner,
            bibliografia, note, id, id_utente, status)
            VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13)
            ON CONFLICT (id,id_utente) DO UPDATE SET id_bene=(SELECT id_bene FROM tmp_funzione),
            lotto=(SELECT lotto FROM tmp_funzione), denominazione=(SELECT denominazione FROM tmp_funzione),
            data=(SELECT data FROM tmp_funzione), tipodata=(SELECT tipo_data FROM tmp_funzione),
            funzione=(SELECT funzione FROM tmp_funzione), id_bener=(SELECT id_bener FROM tmp_funzione),
            denominazioner=(SELECT id_bene FROM tmp_funzione), bibliografia=(SELECT id_bene FROM tmp_funzione),
            note=(SELECT denominazioner FROM tmp_funzione), id=(SELECT id FROM tmp_funzione),
            id_utente=(SELECT id_utente FROM tmp_funzione), status=(SELECT status FROM tmp_funzione)";
    return runPreparedQuery($conn, $stmtID, $query, [$id, $id_utente]);
}

/* /
 * Prepara e esegue una query. Restituisce un dizionario con chiavi:
 * ok: vero se è andata a buon fine, falso altrimenti
 * data: contiene il risultato della query preparata (è il risultato di pg_get_result(...))
 */

function runPreparedQuery($conn, $stmtID, $query, $paramsArr) {
    $res = ['ok' => false, 'data' => array()];
    $result = pg_prepare($conn, $stmtID, $query);
    if ($result) {
        pg_send_execute($conn, $stmtID, $paramsArr);
        $result = pg_get_result($conn);
        $error = pg_result_error($result);
        $res['data'] = $result;
        if ($error == '') {
            $res['ok'] = true;
        }
    }
    return $res;
}

//controlla che le query preparate eseguite siano andate a buon fine. I null sono ignorati
function checkAllPreparedQuery($pQueryArr) {
    $ok = true;
    foreach ($pQueryArr as $value) {
        if (isset($value))
            $ok = $ok && $value['ok'];
    }
    return $ok;
}

//da una lista di query preparate eseguite ottiene la prima con un errore
function getFirstFailedQuery($pQueryArr) {
    $query = null;
    foreach ($pQueryArr as $value) {
        if (isset($value) && !$value['ok']) {
            $query = $value;
            return $query;
        }
    }
}

function getIdFunzione($query) {
    $idFunzione = null;
    if ($query['ok']) {
        $row = pg_fetch_row($query['data']);
        $idFunzione = $row ? $row[0] : null;
    }
    return $idFunzione;
}
