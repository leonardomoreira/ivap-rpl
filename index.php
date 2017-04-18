<!doctype html>
<html lang="pt-BR" class="no-js">
    
<head>
    
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="chrome=1">

    <title></title>
    
    <meta name="author" content="www.leonardomoreira.com.br">
    
    <style>
        body {
            font-family: "Calibri", "Arial", sans-serif;
            font-size: 14px;
        }
    </style>

</head>

<body>

    <?php

        require_once "app.class/Actions.class.php";

        $actions  = new Actions("app.rpl/Cia_AZU_CS.txt");

        $rows    = $actions->init();
        $flights = $actions->flights($rows);

        $actions->write($flights);

    ?>
    
</body>
</html>
