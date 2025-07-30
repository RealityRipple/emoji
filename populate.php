<?php
 header('content-type: text/plain');

 $rSz = 112;
 $fonts = array(
  'twemoji' => 'twemoji',
  'openmoji' => 'openmoji',
  'noto' => 'noto-emoji',
  'blob' => 'blobmoji',
  'facebook' => 'fbmoji',
  'joypixels' => 'joypixels',
  'apple' => 'apple-emoji-linux',
  'tossface' => 'tossface',
  'whatsapp' => 'whatsapp-emoji-linux',
  'oneui' => 'oneui-emoji');

 $redirs = array();
 foreach ($fonts as $font => $fontPath)
 {
  @mkdir($font);
  if (file_exists('sources/'.$fontPath.'/third_party/noto_emoji/emoji_aliases.txt'))
   $redirs[$font] = fileReadAlias($fontPath.'/third_party/noto_emoji/');
  else if (file_exists('sources/'.$fontPath.'/emoji_aliases.txt'))
   $redirs[$font] = fileReadAlias($fontPath);
 }
 copy('sources/twemoji/LICENSE', './LICENSE-TWEMOJI');
 copy('sources/twemoji/LICENSE-GRAPHICS', './LICENSE-TWEMOJI-GRAPHICS');
 copy('sources/openmoji/LICENSE.txt', './LICENSE-OPENMOJI');
 copy('sources/noto-emoji/LICENSE', './LICENSE-NOTO+BLOB');
 copy('sources/fbmoji/LICENSE', './LICENSE-FACEBOOK');
 copy('sources/joypixels/LICENSE.md', './LICENSE-JOYPIXELS');
 copy('sources/tossface/LICENSE', './LICENSE-TOSSFACE');
 copy('sources/whatsapp-emoji-linux/LICENSE', './LICENSE-WHATSAPP');
 copy('sources/oneui-emoji/LICENSE', './LICENSE-ONEUI');

 $GLOBALS['stats'] = array(
  'unqualified' => -1,
  'minimally-qualified' => 0,
  'fully-qualified' => 1
 );

 $ver = 'latest';
 if (array_key_exists('v', $_GET))
  $ver = preg_replace('/[^0-9\.]/', '', $_GET['v']);
 $safeSkip = '';
 $db = dbReadTXT($ver, $safeSkip);
 dbGroupAliases($db);


 $ct = count(array_keys($db));
 $iIDX = 0;
 $skipped = array();
 foreach ($db as $k => $v)
 {
  $iIDX++;
  if (!array_key_exists('name', $v))
   continue;
  $pct = strval(floor(($iIDX/$ct) * 100));
  while (strlen($pct) < 2)
   $pct = '0'.$pct;
  $code = strtolower($k);
  $name = $v['name'];
  echo "[$pct%] Copying $name...";

  $ftFiles = array();
  foreach ($fonts as $font => $fontPath)
  {
   $fRedir = false;
   if (array_key_exists($font, $redirs))
    $fRedir = $redirs[$font];

   $fAlias = false;
   if (array_key_exists('aliases', $v))
    $fAlias = $v['aliases'];

   $eName = emojiParse($code, $font, $fontPath, $name, $fAlias, $fRedir);

   if (!!$eName)
   {
    $ftFiles[$font] = $eName;
    continue;
   }

   echo " Fail!\n      $code not found in $font";
   if ($v['ver'] !== $safeSkip)
    die("\n");
   echo " - Skipping Latest (v$safeSkip)\n";
   $skipped[] = $code;

   unset($db[$k]);
   if (!!$fAlias)
   {
    foreach ($fAlias as $alias)
     unset($db[$alias]);
   }

   break;
  }

  $ftCt = count($fonts);
  if (count(array_keys($ftFiles)) !== $ftCt)
   continue;

  $ftI = 0;
  foreach ($ftFiles as $font => $eName)
  {
   $ftI++;
   $async = (($iIDX % 5) > 0) | ($ftI < $ftCt);
   if (substr($eName, -4) === '.png')
    shrinkPNG($eName, "$font/$code.png", $rSz, $async);
   else if (substr($eName, -4) === '.svg')
    makePNGFromSVG($eName, "$font/$code.png", $rSz, $async);
   else
    die(" Fail!\n      Unknown File Type: $eName");
  }
  echo " Done!\n";
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
   if (array_key_exists($dbMin[$id]['status'], $GLOBALS['stats']))
    $dbMin[$id]['s'] = $GLOBALS['stats'][$dbMin[$id]['status']];
   unset($dbMin[$id]['status']);
  }
  if (count(array_keys($dbMin[$id])) === 0)
   $dbMin[$id] = 1;
 }
 file_put_contents('list.min.json', json_encode($dbMin));

 if (count($skipped) > 0)
  echo 'Skipped '.count($skipped)." Emoji(s) from v$safeSkip (Latest)\n";

 echo "Process Complete.\n";


 function fileReadAlias($source)
 {
  $tmpR = explode("\n", file_get_contents('sources/'.$source.'/emoji_aliases.txt'));
  $r = array();
  for ($i = 0; $i < count($tmpR); $i++)
  {
   if (empty($tmpR[$i]))
    continue;
   if (substr($tmpR[$i], 0, 1) === '#')
    continue;
   if (!str_contains($tmpR[$i], ';'))
    continue;
   $from = substr($tmpR[$i], 0, strpos($tmpR[$i], ';'));
   $to = substr($tmpR[$i], strpos($tmpR[$i], ';') + 1);
   if (str_contains($to, '#'))
    $to = substr($to, 0, strpos($to, '#'));
   $from = strtolower(trim($from));
   $to = strtolower(trim($to));
   $from = implode('-', explode('_', $from));
   $to = implode('-', explode('_', $to));
   $r[$from] = $to;
  }
  return $r;
 }

 function dbReadTXT($v, &$safeSkip)
 {
  $db = array();
  $group = 'Ungrouped';
  $subgroup = 'none';
  $data = file_get_contents('https://unicode.org/Public/emoji/'.$v.'/emoji-test.txt');
  $lns = explode("\n", $data);
  $safeSkip = $v;
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
   if (!array_key_exists($status, $GLOBALS['stats']))
    continue;
   if ($GLOBALS['stats'][$status] < 1)
    $db[$codeStd] = array('target' => $name, 'status' => $status);
   else
    $db[$codeStd] = array('name' => $name, 'ver' => $version, 'group' => $group, 'subgroup' => $subgroup);
  }
  return $db;
 }

 function dbGroupAliases(&$db)
 {
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
  return $db;
 }

 function emojiParse($codeStd, $font, $fontPath, $name = false, $aliases = false, $redirs = false)
 {
  $codeCB = 'code_'.$font;
  $nameCB = 'name_'.$font;

  $fList = array();

  $fRet = call_user_func($codeCB, $codeStd, $fontPath, $redirs);
  if ($fRet !== false)
   $fList = array_merge($fList, $fRet);

  if ($name !== false)
  {
   $fRet = call_user_func($nameCB, $name, $fontPath);
   if ($fRet !== false)
    $fList = array_merge($fList, $fRet);
  }

  if ($aliases !== false)
  {
   foreach ($aliases as $alias)
   {
    $fRet = call_user_func($codeCB, $alias, $fontPath, $redirs);
    if ($fRet !== false)
     $fList = array_merge($fList, $fRet);
   }
  }

  foreach ($fList as $test)
  {
   if (file_exists($test))
   {
    return $test;
    break;
   }
  }
  return false;
 }

 function code_twemoji($codeStd, $fontPath, $redirs = false)
 {
  $c = ltrim($codeStd, '0');
  if ($redirs !== false && array_key_exists($codeStd, $redirs))
   $c = ltrim($redirs[$codeStd], '0');
  $r = array();
  $r[] = "sources/$fontPath/assets/svg/$c.svg";
  return $r;
 }

 function name_twemoji($name, $fontPath){return false;}

 function code_openmoji($codeStd, $fontPath, $redirs = false)
 {
  $c = strtoupper($codeStd);
  if ($redirs !== false && array_key_exists($codeStd, $redirs))
   $c = strtoupper($redirs[$codeStd]);
  $r = array();
  $r[] = "sources/$fontPath/color/svg/$c.svg";
  return $r;
 }

 function name_openmoji($name, $fontPath){return false;}

 function code_noto($codeStd, $fontPath, $redirs = false)
 {
  $c = 'emoji_u'.strtolower(implode('_', explode('-', $codeStd)));
  if ($redirs !== false && array_key_exists($codeStd, $redirs))
   $c = 'emoji_u'.strtolower(implode('_', explode('-', $redirs[$codeStd])));
  $r = array();
  $r[] = "sources/$fontPath/svg/$c.svg";
  $r[] = "sources/$fontPath/third_party/region-flags/waved-svg/$c.svg";
  return $r;
 }

 function name_noto($name, $fontPath){return false;}

 function code_blob($codeStd, $fontPath, $redirs = false)
 {
  $r = array();
  $n = 'emoji_u'.strtolower(implode('_', explode('-', $codeStd)));
  if ($redirs !== false && array_key_exists($codeStd, $redirs))
   $n = 'emoji_u'.strtolower(implode('_', explode('-', $redirs[$codeStd])));
  $r[] = "sources/$fontPath/svg/$n.svg";
  $r[] = "sources/$fontPath/svg15-1/$n.svg";
  $r[] = "sources/noto-emoji/third_party/region-flags/waved-svg/$n.svg";
  return $r;
 }

 function name_blob($name, $fontPath)
 {
  $r = array();
  $n = strtolower(preg_replace('/[^\p{Lu}\p{Ll}0-9\- ]/u', '', $name));
  $r[] = "sources/$fontPath/svg/$n.svg";
  $r[] = "sources/$fontPath/svg15-1/$n.svg";
  return $r;
 }

 function code_facebook($codeStd, $fontPath, $redirs = false)
 {
  $r = array();
  $n = ltrim(strtolower(implode('_', explode('-', $codeStd))), '0');
  if ($redirs !== false && array_key_exists($codeStd, $redirs))
   $n = ltrim(strtolower(implode('_', explode('-', $redirs[$codeStd]))), '0');
  $r[] = "sources/$fontPath/png/$n.png";
  return $r;
 }

 function name_facebook($name, $fontPath){return false;}

 function code_joypixels($codeStd, $fontPath, $redirs = false)
 {
  $r = array();
  $n = str_replace('-200d', '', $codeStd);
  if ($redirs !== false && array_key_exists($codeStd, $redirs))
   $n = str_replace('-200d', '', $redirs[$codeStd]);
  $r[] = "sources/$fontPath/png/128/$n.png";
  return $r;
 }

 function name_joypixels($name, $fontPath){return false;}

 function code_apple($codeStd, $fontPath, $redirs = false)
 {
  $r = array();
  $n = 'emoji_u'.strtolower(implode('_', explode('-', $codeStd)));
  if ($redirs !== false && array_key_exists($codeStd, $redirs))
   $n = 'emoji_u'.strtolower(implode('_', explode('-', $redirs[$codeStd])));
  $r[] = "sources/$fontPath/png/160/$n.png";
  return $r;
 }

 function name_apple($name, $fontPath){return false;}

 function code_tossface($codeStd, $fontPath, $redirs = false)
 {
  $r = array();
  $n = str_replace('U', 'u', strtoupper('U'.implode('_U', explode('-', $codeStd))));
  if ($redirs !== false && array_key_exists($codeStd, $redirs))
   $n = str_replace('U', 'u', strtoupper('U'.implode('_U', explode('-', $redirs[$codeStd]))));
  $r[] = "sources/$fontPath/dist/svg/$n.svg";
  return $r;
 }

 function name_tossface($name, $fontPath){return false;}

 function code_whatsapp($codeStd, $fontPath, $redirs = false)
 {
  $r = array();
  $n = 'emoji_u'.strtolower(implode('_', explode('-', $codeStd)));
  if ($redirs !== false && array_key_exists($codeStd, $redirs))
   $n = 'emoji_u'.strtolower(implode('_', explode('-', $redirs[$codeStd])));
  $r[] = "sources/$fontPath/png/160/$n.png";
  return $r;
 }

 function name_whatsapp($name, $fontPath){return false;}

 function code_oneui($codeStd, $fontPath, $redirs = false)
 {
  $r = array();
  $r[] = "sources/$fontPath/png/112/$codeStd.png";
  return $r;
 }

 function name_oneui($name, $fontPath){return false;}

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

 function shrinkPNG($src, $png, $h = 112, $async = true)
 {
  $convert = 'convert';
  $convert.= ' -resize 112x112^';
  $convert.= ' -strip';
  $convert.= ' -define png:compression-level=9';
  $convert.= ' "'.$src.'"';
  $convert.= ' "png32:'.$png.'"';
  if ($async)
   exec("bash -c 'exec nohup setsid $convert > /dev/null 2>&1 &'");
  else
   exec($convert);
 }
?>