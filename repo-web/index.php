<?php

    if(intval(date("n")) != 12) {
        echo "Reviens en Décembre petit futé";
        //exit();
    }

    $content = "<table>";
    $nextTr = true;
    $nbOfCols = 6;

    for ($i=1; $i <= 24; $i++) {

        if($nextTr) {
            $nextTr = false;
            $content .= "<tr>";
        }

        $content .= "<td>";
        if($i < intval(date("j"))+1) {
            $content .= getContent($i);
        } else {
          $content .= "jour ".$i." indisponible";
        }
        $content .= "</td>";
        if($i % $nbOfCols == 0) {
            $content .= "</tr>";
            $nextTr = true;
        }
    }
    $content .= "</table>";

    // ajouter jour 25 ici

    echo $content;

    function getContent($dayNb) {
        return "mon contenu pour le jour " . $dayNb;
    }
?>
