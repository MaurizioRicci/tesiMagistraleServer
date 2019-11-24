<?php

include('connection.php');
include('utils.php');
include('queryUtils.php');
header('Content-type: application/json');
$My_POST = postEmptyStr2NULL();
$c = 0;
http_response_code(500);
$res = [];

$user = risolviUtente($conn, $c++, $My_POST['username'], $My_POST['password']);
if (!isset($user)) {
    http_response_code(401);
    $res['msg'] = 'Username/Password invalidi';
    $error = true;
} else {
    $getUserStats = "with id_min_max as (select id_min, id_max from utenti where gid=$1),
        ultimo_id_bene as (
        select max(id_bene) as ultimo_id_bene
        from (
          select id_bene from public.manipola_bene where id_utente=$1
          union
          select id from tmp_db.benigeo where id_utente=$1
        ) as _
      ),
      n_beni_rev as (
              select count(*) as n_beni_rev
        from tmp_db.benigeo where id_utente=$1 and status=0
      ),
      n_beni_incompleti as (
              select count(*) as n_beni_incompleti
        from tmp_db.benigeo where id_utente=$1 and status=3
      ),
      n_beni_da_correggere as (
              select count(*) as n_beni_da_correggere
        from tmp_db.benigeo where id_utente=$1 and status=1
      ),
      n_funzioni_rev as (
              select count(*) as n_funzioni_rev
        from tmp_db.benigeo where id_utente=$1 and status=0
      ),
      n_funzioni_incomplete as (
              select count(*) as n_funzioni_incomplete
        from tmp_db.benigeo where id_utente=$1 and status=3
      ),
      n_funzioni_da_correggere as (
              select count(*) as n_funzioni_da_correggere
        from tmp_db.benigeo where id_utente=$1 and status=1
      )
      select *
      from id_min_max, ultimo_id_bene, n_beni_rev, n_beni_incompleti, n_beni_da_correggere,
              n_funzioni_rev,n_funzioni_incomplete, n_funzioni_da_correggere";

    $resp = runPreparedQuery($conn, $c++, $getUserStats, [$user['id']]);
    if ($resp['ok']) {
        http_response_code(200);
        $res = pg_fetch_assoc($resp['data']);
    }
}

echo json_encode($res);