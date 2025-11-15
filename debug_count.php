<?php
require 'backend/connection.php';
$stmt = $pdo->query("SELECT COUNT(*) FROM public.approved_docs ad WHERE NULLIF(btrim(ad.no),'') IS NOT NULL");
var_export($stmt->fetchColumn());
