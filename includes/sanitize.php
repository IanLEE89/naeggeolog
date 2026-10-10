<?php
require_once __DIR__.'/../config.php';

/**
 * 본문 HTML 정화 — 허용한 태그/속성만 남기고 script·iframe·on* 속성 등은 제거.
 * (관리자 계정이 탈취되거나 외부 글을 붙여넣어도 방문자 브라우저에서 코드가 실행되지 않게 함)
 */
function fd_sanitize_html($html){
  $html=trim((string)$html); if($html==='') return '';
  $allowed=['p'=>[],'br'=>[],'strong'=>[],'b'=>[],'em'=>[],'i'=>[],'u'=>[],'del'=>[],'s'=>[],'strike'=>[],'ul'=>[],'ol'=>[],'li'=>[],'a'=>['href','title'],'h3'=>[],'h4'=>[],'blockquote'=>[],'span'=>['style'],'font'=>['color'],'div'=>[],'hr'=>[]];
  $drop=['script','style','iframe','object','embed','form','input','button','textarea','select','option','link','meta','svg','math','noscript','template','base'];
  if(!class_exists('DOMDocument')){ // DOM 확장이 없을 때의 보수적 대체
    $t=strip_tags($html,'<'.implode('><',array_keys($allowed)).'>');
    $t=preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$t);
    return preg_replace('/(href\s*=\s*["\']?)\s*(javascript|data|vbscript):/i','$1#blocked:',$t);
  }
  $doc=new DOMDocument('1.0','UTF-8'); $prev=libxml_use_internal_errors(true);
  $doc->loadHTML('<?xml encoding="UTF-8"><div id="fd-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
  libxml_clear_errors(); libxml_use_internal_errors($prev);
  $root=null; foreach($doc->getElementsByTagName('div') as $d){ if($d->getAttribute('id')==='fd-root'){$root=$d;break;} }
  if(!$root) return htmlspecialchars(strip_tags($html),ENT_QUOTES,'UTF-8');
  $clean=function(DOMNode $node) use (&$clean,$allowed,$drop){
    foreach(iterator_to_array($node->childNodes) as $c){
      if($c->nodeType===XML_COMMENT_NODE||$c->nodeType===XML_PI_NODE){ $node->removeChild($c); continue; }
      if($c->nodeType!==XML_ELEMENT_NODE) continue;
      $tag=strtolower($c->nodeName);
      if(in_array($tag,$drop,true)){ $node->removeChild($c); continue; }
      $clean($c);
      if(!isset($allowed[$tag])){ // 허용되지 않은 태그는 껍데기만 벗기고 내용은 유지
        while($c->firstChild) $node->insertBefore($c->firstChild,$c);
        $node->removeChild($c); continue;
      }
      foreach(iterator_to_array($c->attributes) as $attr){
        $n=strtolower($attr->nodeName); $v=trim($attr->nodeValue);
        $keep=in_array($n,$allowed[$tag],true);
        if($keep&&$n==='href'&&!preg_match('~^(https?://|mailto:|tel:|#|/)~i',$v)) $keep=false;
        if($keep&&$n==='color'&&!preg_match('/^#[0-9a-f]{3,8}$|^[a-z]+$/i',$v)) $keep=false;
        if($keep&&$n==='style'){
          $ok=[]; foreach(explode(';',$v) as $decl){ if(preg_match('/^\s*(color|background-color)\s*:\s*(#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[\d.]+\s*)?\))\s*$/i',$decl,$m)) $ok[]=strtolower($m[1]).':'.$m[2]; }
          if($ok) $attr->nodeValue=implode(';',$ok); else $keep=false;
        }
        if(!$keep) $c->removeAttribute($attr->nodeName);
      }
      if($tag==='a'&&$c->hasAttribute('href')){ $c->setAttribute('rel','noopener noreferrer'); }
    }
  };
  $clean($root);
  $out=''; foreach($root->childNodes as $c) $out.=$doc->saveHTML($c);
  return trim($out);
}


/** 정화 결과를 cache/html 에 저장해 같은 본문은 한 번만 처리(이미 정화된 글도 안전하게 한 번 더 거침). */
function html_clean_cached($raw){
  $raw=(string)$raw; if(trim($raw)==='') return '';
  $k=sha1($raw); $f=rtrim(CACHE_DIR,'/\\').'/html/'.substr($k,0,2).'/'.$k.'.html';
  if(is_file($f)) return (string)file_get_contents($f);
  $c=fd_sanitize_html($raw);
  $d=dirname($f); if(is_dir($d)||@mkdir($d,0775,true)) @file_put_contents($f,$c,LOCK_EX);
  return $c;
}
