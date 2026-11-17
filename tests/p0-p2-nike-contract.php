<?php
/** GER-EDITORIAL-2026-01 · Nike P2 and SEO contract (no WordPress writes). */
declare(strict_types=1);
define('ABSPATH','/tmp/wordpress');
$root = getenv('GERIZIM_SOURCE') ?: dirname(__DIR__);
function wp_strip_all_tags(string $text):string { return strip_tags($text); }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $s):string { return strtolower($s); } }
function remove_accents(string $text):string { return strtr($text,['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']); }
class IDG_Internal_Links {
    public static function normalize(array $workflow):array {
        return [['url'=>'https://ideasdi.com/tag/calzado/','source_name'=>'Calzado']];
    }
}
require $root.'/includes/class-editorial-document.php';
require $root.'/includes/class-final-guard.php';
$phrase = new ReflectionMethod(IDG_Editorial_Document::class,'phrases');
$anchor = new ReflectionMethod(IDG_Editorial_Document::class,'valid_internal_anchor');
$report = new ReflectionMethod(IDG_Editorial_Document::class,'remove_fulfilled_link_suggestions');
$lens = new ReflectionMethod(IDG_Final_Guard::class,'editorial_lens_visible');
$text = 'La organización interna, entonces, tiene un efecto claro sobre la lectura del calzado: menos arbitrariedad en la forma.';
$workflow = ['keyword'=>'Nike Tuned Air'];
$seo = "- Enlaces aplicados: 1 interno; 1 externo\n- 3 mejoras sugeridas:\n  1. Integrar el enlace interno resuelto.\n  2. Insertar el enlace externo oficial.\n  3. Revisar la caja editorial.";
$success=0; $total=0;
$check = static function(string $name,bool $ok)use(&$success,&$total):void {
    ++$total; if($ok) ++$success;
    echo ($ok?'PASS':'FAIL')." $name\n";
};
$choices=$phrase->invoke(null,$text,['calzado'],true);
$check('Nike nominal anchor',in_array('la lectura del calzado',$choices,true));
$check('Never cross clause punctuation',!in_array('lectura del calzado: menos arbitrariedad',$choices,true));
$check('Legacy colon anchor invalid',!$anchor->invoke(null,'lectura del calzado: menos arbitrariedad',$workflow,'https://ideasdi.com/tag/calzado/'));
$check('Clean nominal anchor valid',$anchor->invoke(null,'la lectura del calzado',$workflow,'https://ideasdi.com/tag/calzado/'));
$clean=$report->invoke(null,$seo,true,true);
$check('Completed insertions removed',!str_contains($clean,'Integrar el enlace interno')&&!str_contains($clean,'Insertar el enlace externo'));
$check('Remaining suggestion preserved and renumbered',str_contains($clean,'- 1 mejora sugerida:')&&str_contains($clean,'1. Revisar la caja editorial.'));
$check('Unfulfilled requests retained',$report->invoke(null,$seo,false,false)===$seo);
$check('One fulfilled action removed only',$report->invoke(null,$seo,false,true)!==$seo&&str_contains($report->invoke(null,$seo,false,true),'Integrar el enlace interno'));
$check('Report normalization idempotent',$report->invoke(null,$clean,true,true)===$clean);
$check('Lens description does not cause false alert',$lens->invoke(null,'Calzado, con foco en construcción de suela','nike tuned air estudia el calzado bajo el pie'));
$check('Absent lens remains visible as an omission',!$lens->invoke(null,'Calzado, con foco en construcción de suela','la pieza es un tejido textil'));
$check('No false substring match',!$lens->invoke(null,'Calzado','el diseño descalzado no es la disciplina'));
echo "SUMMARY $success/$total\n";
exit($success===$total?0:1);
