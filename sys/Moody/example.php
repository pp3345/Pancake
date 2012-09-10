<?
// .config("deletewhitespaces", true) Set this to false to get human-readable code
// .def("ABC", 7)
// .ifdef("ABC")
 echo 'Notch';
// .endif
// .ifdef("SOME_UNDEFINED_CONSTANT")
 echo 'This should never show up in the code';
// .endif
// .ifdef("ABC")
 // .def("BEST_SOFTWARE", "Pancake")
 // .ifdef("BEST_SOFTWARE")
 echo 'Nested ifs are working! :D';
  // .ifdef("TROLL")
   echo 'Not good.';
  // .else
   echo 'Else is working! :D';
  // .endif
 // .endif
// .endif
// Normal comment
echo 'Some Code';
echo /* .constant("ABC") */;
// .undefine("ABC")
// .label("someLabel")
// .ifdef("ABC")
 echo 'Trolol';
// .endif
// .ifndef("ABC")
 // .def("ABC", 'Pancake')
 echo /* .constant('ABC') */;
// .endif
// .ifndef("JUMPED_TO_SOMELABEL")
 // .def("JUMPED_TO_SOMELABEL", true)
 // .goto("someLabel")
// .endif
// .if(JUMPED_TO_SOMELABEL == 1)
 echo 'if is working :D';
 // .if(JUMPED_TO_SOMELABEL !== 1)
  echo 'if is really working :D';
 // .endif
// .endif
// .if(PHP_OS == "WINNT")
 echo 'You are using Windows! :O';
// .endif
// .if(/* .constant("T_LNUMBER") */ == 305)
 echo 'Inline instructions are working :D';
// .endif
// .if(/* .isDefined("PHP_OS") */)
 echo '.isDefined = working.';
// .endif
// .macro("myMacro", 'for($i = 0; $i < 10; $i++) echo "Pancake";')
// .macro("complexMacro", 'if($n) echo $x;', '$n', '$x')
 echo 'blabla';
// .myMacro

$a = 4;
$b = "Hello world!";
// .complexMacro($a, $b)
// .if(true == false)
 myfunc();
// .elseif(true == true)
 myfunc2();
// .endif
// .if(/* .eval('return mt_rand(0, 100) > 50; // .constant', false) */)
 echo 'mt_rand() returned a value greater than 50!';
 // .endif
// .config('deletecomments', true)
// .if(/* .config('deletecomments') */ === true)
 echo 'deletecomments activated';
 // This comment will not show up in the code
// .endif

 // Some very interesting text.Although there is a dot, this should not be parsed as an instruction
// .inc("example.inc.php")

 // .config("compressvariables", true)
 $aVeryLongName = 7;
 $blubb = 8;
 echo $aVeryLongName;
 // .mapVariable('$moody', '$pancake')
 $moody = 7;
 echo func($moody);
 // raiseError("OH MY GOD WE ARE OUT OF PANCAKES!!")
 // ^ Add a dot here and see a nice error
// .halt
// .unhandledInstruction
?>
