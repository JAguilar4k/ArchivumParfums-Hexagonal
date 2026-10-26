<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Business rules that no test names yet
|--------------------------------------------------------------------------
|
| Each rule of section 5 of docs/especificacion.md waits here until a test
| names it at the start of its description, as in it('R14: ...'). Each
| value says the delivery cut in which the rule is first due and the
| issues of that cut that cite it. The issue that names a rule in a test
| removes it from this list, and BusinessRulesTest fails while a named
| rule stays. The list is reviewed before every delivery tag (#16); in
| v-final every rule that stays here says why.
|
*/

return [
    'R1' => 'avance-1: #33, #42, #43',
    'R2' => 'entrega-final: #175',
    'R4' => 'avance-1: #33',
    'R5' => 'avance-1: #36, #37, #39, #40, #48, #49',
    'R6' => 'entrega-final: #173, #174, #195',
    'R11' => 'avance-1: #51, #52, #53, #54',
    'R12' => 'avance-1: #58, #62, #67',
    'R13' => 'avance-1: #55, #56, #57, #61, #62, #65, #67',
    'R14' => 'avance-1: #58, #59, #60, #63, #65, #67',
    'R15' => 'avance-1: #62, #67',
    'R16' => 'avance-1: #58, #65, #66',
    'R17' => 'avance-2: #138',
    'R18' => 'avance-1: #25, #33, #47, #62',
    'R19' => 'entrega-final: #149, #151',
    'R20' => 'entrega-final: #149, #152',
    'R21' => 'entrega-final: #149, #152, #153, #154, #160',
    'R22' => 'entrega-final: #155, #163',
    'R27' => 'entrega-final: #177; #87 comprueba líneas ya recibidas',
    'R32' => 'entrega-final: #142, #144',
    'R33' => 'entrega-final: #142, #145, #159, #160',
    'R34' => 'entrega-final: #142, #143, #146, #147',
    'R35' => 'entrega-final: #142, #146, #148, #159',
    'R38' => 'avance-2: #94, #95; Investigación 1 confirma por consulta autenticada',
    'R39' => 'avance-2: #96, #192',
    'R41' => 'entrega-final: #170, #171, #172',
    'R42' => 'entrega-final: #170, #176, #177',
    'R43' => 'entrega-final: #175, #178',
    'R44' => 'entrega-final: #170, #177',
    'R45' => 'entrega-final: #161, #162, #163, #164, #196',
    'R46' => 'avance-2: #95',
    'R47' => 'entrega-final: #167, #168, #169',
    'R48' => 'avance-2: #117, #118',
    'R49' => 'avance-2: #119',
    'R50' => 'avance-2: #117, #120',
    'R51' => 'avance-2: #110, #121',
    'R52' => 'avance-2: #122',
    'R53' => 'avance-1: #44',
];
