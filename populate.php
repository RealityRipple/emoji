<?php
 header('content-type: text/plain');
 $v = 'latest';
 if (array_key_exists('v', $_GET))
  $v = preg_replace('/[^0-9\.]/', '', $_GET['v']);
 $stats = array(
  'unqualified' => -1,
  'minimally-qualified' => 0,
  'fully-qualified' => 1
 );
 $safeSkip = $v;
 $data = file_get_contents('https://unicode.org/Public/emoji/'.$v.'/emoji-test.txt');
 $lns = explode("\n", $data);
 $tmpBR = explode("\n", file_get_contents('sources/blobmoji/emoji_aliases.txt'));
 $blobRedir = array();
 for ($i = 0; $i < count($tmpBR); $i++)
 {
  if (empty($tmpBR[$i]))
   continue;
  if (substr($tmpBR[$i], 0, 1) === '#')
   continue;
  if (!str_contains($tmpBR[$i], ';'))
   continue;
  $from = substr($tmpBR[$i], 0, strpos($tmpBR[$i], ';'));
  $to = substr($tmpBR[$i], strpos($tmpBR[$i], ';') + 1);
  if (str_contains($to, '#'))
   $to = substr($to, 0, strpos($to, '#'));
  $from = strtolower(trim($from));
  $to = strtolower(trim($to));
  $from = implode('-', explode('_', $from));
  $to = explode('_', $to);
  $blobRedir[$from] = $to;
 }
 $db = array();
 $group = 'Ungrouped';
 $subgroup = 'none';
 $rSz = 112;
 @mkdir('twemoji');
 @mkdir('openmoji');
 @mkdir('noto');
 @mkdir('blob');
 copy('sources/twemoji/LICENSE', './LICENSE-TWEMOJI');
 copy('sources/twemoji/LICENSE-GRAPHICS', './LICENSE-TWEMOJI-GRAPHICS');
 copy('sources/openmoji/LICENSE.txt', './LICENSE-OPENMOJI');
 copy('sources/noto-emoji/LICENSE', './LICENSE-NOTO+BLOB');
 for ($i = 0; $i < count($lns); $i++)
 {
  if (empty($lns[$i]))
   continue;
  if (substr($lns[$i], 0, 1) === '#')
  {
   if (strlen($lns[$i]) > 11 && substr($lns[$i], 0, 11) === '# Version: ')
    $safeSkip = trim(substr($lns[$i], 11));
   if (strlen($lns[$i]) > 9 && substr($lns[$i], 0, 9) === '# group: ')
    $group = trim(substr($lns[$i], 9));
   if (strlen($lns[$i]) > 12 && substr($lns[$i], 0, 12) === '# subgroup: ')
    $subgroup = trim(substr($lns[$i], 12));
   continue;
  }
  if (!str_contains($lns[$i], ';'))
   continue;
  if (!str_contains($lns[$i], '#'))
   continue;
  $code = explode(' ', trim(substr($lns[$i], 0, strpos($lns[$i], ';'))));
  $status = substr($lns[$i], strpos($lns[$i], ';') + 1);
  $status = trim(substr($status, 0, strpos($status, '#')));
  $details = explode(' ', trim(substr($lns[$i], strpos($lns[$i], '#') + 1)), 3);
  if (count($details) !== 3)
   continue;
  $value = $details[0];
  $version = $details[1];
  if (substr($version, 0, 1) !== 'E')
   continue;
  $version = substr($version, 1);
  $name = $details[2];
  $codeStd = strtolower(implode('-', $code));
  if (!array_key_exists($status, $stats))
   continue;
  if ($stats[$status] < 1)
   $db[$codeStd] = array('target' => $name, 'status' => $status);
  else
   $db[$codeStd] = array('name' => $name, 'ver' => $version, 'group' => $group, 'subgroup' => $subgroup);
 }
 foreach ($db as $k => $v)
 {
  if (!array_key_exists('target', $v))
   continue;
  $found = false;
  foreach ($db as $k2 => $v2)
  {
   if (!array_key_exists('name', $v2))
    continue;
   if ($v2['name'] !== $v['target'])
    continue;
   $db[$k]['target'] = $k2;
   if (!array_key_exists('aliases', $v2))
    $db[$k2]['aliases'] = array();
   $db[$k2]['aliases'][] = strval($k);
   $found = true;
   break;
  }
  if (!$found)
   unset($db[$k]);
 }
 $skipped = array();
 $ct = count(array_keys($db));
 $iIDX = 0;
 foreach ($db as $k => $v)
 {
  $iIDX++;
  if (!array_key_exists('name', $v))
   continue;
  $code = explode('-', $k);
  $name = $v['name'];
  $codeStd = strtolower(implode('-', $code));
  $codeCap = strtoupper(implode('-', $code));
  $codeNoZ = ltrim($codeStd, '0');
  $codeE = 'emoji_u'.strtolower(implode('_', $code));
  $codeN = strtolower(preg_replace('/[^\p{Lu}\p{Ll}0-9\- ]/u', '', $name));
  $blobE = $codeE;
  if (array_key_exists($codeStd, $blobRedir))
   $blobE = 'emoji_u'.strtolower(implode('_', $blobRedir[$codeStd]));
  $pct = floor(($iIDX/$ct) * 100);
  echo "[$pct%] Copying $name ($codeStd)...\n";

  $tName = false;
  $tList = array($codeNoZ);
  if (array_key_exists('aliases', $v))
  {
   foreach ($v['aliases'] as $alias)
    $tList[] = ltrim(strtolower($alias), '0');
  }
  foreach ($tList as $test)
  {
   $path = "sources/twemoji/assets/svg/$test.svg";
   if (file_exists($path))
   {
    $tName = $path;
    break;
   }
  }
  if (!$tName)
  {
   echo "$codeStd not found in Twemoji";
   if ($v['ver'] !== $safeSkip)
    die("\n");
   echo " - Skipping Latest (v$safeSkip)\n";
   $skipped[] = $codeStd;
   unset($db[$k]);
   if (array_key_exists('aliases', $v))
   {
    foreach ($v['aliases'] as $alias)
     unset($db[$alias]);
   }
   continue;
  }

  $oName = false;
  $oList = array($codeCap);
  if (array_key_exists('aliases', $v))
  {
   foreach ($v['aliases'] as $alias)
    $oList[] = strtoupper($alias);
  }
  foreach ($oList as $test)
  {
   $path = "sources/openmoji/color/svg/$test.svg";
   if (file_exists($path))
   {
    $oName = $path;
    break;
   }
  }
  if (!$oName)
  {
   echo "$codeStd not found in Openmoji";
   if ($v['ver'] !== $safeSkip)
    die("\n");
   echo " - Skipping Latest (v$safeSkip)\n";
   $skipped[] = $codeStd;
   unset($db[$k]);
   if (array_key_exists('aliases', $v))
   {
    foreach ($v['aliases'] as $alias)
     unset($db[$alias]);
   }
   continue;
  }

  $nName = false;
  $nList = array($codeE);
  if (array_key_exists('aliases', $v))
  {
   foreach ($v['aliases'] as $alias)
    $nList[] = 'emoji_u'.strtolower(implode('_', explode('-', $alias)));
  }
  foreach ($nList as $test)
  {
   $path = "sources/noto-emoji/svg/$test.svg";
   if (file_exists($path))
   {
    $nName = $path;
    break;
   }
   $pathF = "sources/noto-emoji/third_party/region-flags/waved-svg/$test.svg";
   if (file_exists($pathF))
   {
    $nName = $pathF;
    break;
   }
  }
  if (!$nName)
  {
   echo "$codeStd not found in Noto";
   if ($v['ver'] !== $safeSkip)
    die("\n");
   echo " - Skipping Latest (v$safeSkip)\n";
   $skipped[] = $codeStd;
   unset($db[$k]);
   if (array_key_exists('aliases', $v))
   {
    foreach ($v['aliases'] as $alias)
     unset($db[$alias]);
   }
   continue;
  }

  $bName = false;
  $bList = array(
   "sources/blobmoji/svg/$blobE.svg",
   "sources/blobmoji/svg/$codeN.svg",
   "sources/blobmoji/svg15-1/$blobE.svg",
   "sources/blobmoji/svg15-1/$codeN.svg",
   "sources/noto-emoji/third_party/region-flags/waved-svg/$codeE.svg"
  );
  if (array_key_exists('aliases', $v))
  {
   foreach ($v['aliases'] as $alias)
   {
    if (array_key_exists($alias, $blobRedir))
     $aliasE = 'emoji_u'.strtolower(implode('_', explode('-', $blobRedir[$alias])));
    else
     $aliasE = 'emoji_u'.strtolower(implode('_', explode('-', $alias)));
    $bList[] = "sources/blobmoji/svg/$aliasE.svg";
    $bList[] = "sources/blobmoji/svg15-1/$aliasE.svg";
    $bList[] = "sources/noto-emoji/third_party/region-flags/waved-svg/$aliasE.svg";
   }
  }
  foreach ($bList as $path)
  {
   if (file_exists($path))
   {
    $bName = $path;
    break;
   }
  }
  if (!$bName)
  {
   echo "$codeStd not found in Blob";
   if ($v['ver'] !== $safeSkip)
    die("\n");
   echo " - Skipping Latest (v$safeSkip)\n";
   $skipped[] = $codeStd;
   unset($db[$k]);
   if (array_key_exists('aliases', $v))
   {
    foreach ($v['aliases'] as $alias)
     unset($db[$alias]);
   }
   continue;
  }
  makePNGFromSVG($tName, "twemoji/$codeStd.png", $rSz);
  makePNGFromSVG($oName, "openmoji/$codeStd.png", $rSz);
  makePNGFromSVG($nName, "noto/$codeStd.png", $rSz);
  makePNGFromSVG($bName, "blob/$codeStd.png", $rSz, false);
 }
 file_put_contents('list.json', json_encode($db, JSON_PRETTY_PRINT));
 $dbMin = array();
 foreach ($db as $id => $v)
 {
  $dbMin[$id] = $v;
  unset($dbMin[$id]['name']);
  unset($dbMin[$id]['ver']);
  unset($dbMin[$id]['group']);
  unset($dbMin[$id]['subgroup']);
  if (array_key_exists('aliases', $dbMin[$id]))
  {
   $dbMin[$id]['a'] = $dbMin[$id]['aliases'];
   unset($dbMin[$id]['aliases']);
  }
  if (array_key_exists('target', $dbMin[$id]))
  {
   $dbMin[$id]['t'] = $dbMin[$id]['target'];
   unset($dbMin[$id]['target']);
  }
  if (array_key_exists('status', $dbMin[$id]))
  {
   if (array_key_exists($dbMin[$id]['status'], $stats))
    $dbMin[$id]['s'] = $stats[$dbMin[$id]['status']];
   unset($dbMin[$id]['status']);
  }
  if (count(array_keys($dbMin[$id])) === 0)
   $dbMin[$id] = 1;
 }
 file_put_contents('list.min.json', json_encode($dbMin));
 if (count($skipped) > 0)
  echo 'Skipped '.count($skipped)." Emoji(s) from v$safeSkip (Latest)\n";
 echo "Process Complete.\n";

 function makePNGFromSVG($svg, $png, $h = 112, $async = true)
 {
  $inkscape = 'inkscape';
  $inkscape.= ' --export-background-opacity=0';
  $inkscape.= ' --export-height='.$h;
  $inkscape.= ' --export-type=png';
  $inkscape.= ' --export-filename="'.$png.'"';
  $inkscape.= ' "'.$svg.'"';
  if ($async)
   exec("bash -c 'exec nohup setsid $inkscape > /dev/null 2>&1 &'");
  else
   exec($inkscape);
 }
?>