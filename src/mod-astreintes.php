<?php

// Fonction principale pour gérer les étapes de l'automatisation des astreintes
function AstreintesAutomate()
{

    switch($_REQUEST['Etape'])
    {
        case 'Nouv' : return astreintesNouveau(); break;
        case 'Edit' : return astreintesModifier(); break;
        case "Cron" : return astreintesCron(); break;
        default :
            return astreintesNouveau();
    }

}

// Affiche le formulaire pour les nouvelles astreintes
function astreintesNouveau(){
    return astreintesFormulaire("");
}

// Modifie les astreintes existantes
function astreintesModifier(){

    global $config;

    try {
        $user = $config['bdd_username'];
        $password = $config['bdd_password'];
        $server = $config['bdd_server'];
        $database = $config['bdd_database'];
        $cn = new PDO("mysql:host=$server;dbname=$database", $user, $password);
    } catch (Exception $e) {
        var_dump($e);
    }
    if (isset($_POST['enregistrer']) && isset($_POST['annee'])) {
        $year = $_POST['annee'];

        foreach ($_POST as $key => $value) {
            if (strpos($key, 'astreinte_')!== false) {
                list($astreinte, $datesSemaine, $year) = explode('_', $key);
                list($date_deb_astreintes, $date_fin_astreintes) = getWeekStartEndDates($datesSemaine, $year);
                $usrid = $value;

                try {
                    $sql = "SELECT COUNT(*) FROM k2_astreintes WHERE date_deb_astreintes = :date_deb_astreintes AND date_fin_astreintes = :date_fin_astreintes AND YEAR(date_deb_astreintes) = :annee";
                    $stmt = $cn->prepare($sql);
                    $stmt->bindParam(':date_deb_astreintes', $date_deb_astreintes);
                    $stmt->bindParam(':date_fin_astreintes', $date_fin_astreintes);
                    $stmt->bindParam(':annee', $year);
                    $stmt->execute();
                    $rowCount = $stmt->fetchColumn();

                    if ($rowCount > 0) {
                        $sql = "UPDATE k2_astreintes SET usrid = :usrid WHERE date_deb_astreintes = :date_deb_astreintes AND date_fin_astreintes = :date_fin_astreintes AND YEAR(date_deb_astreintes) = :annee";                        $stmt = $cn->prepare($sql);
                        $stmt = $cn->prepare($sql);
                        $stmt->bindParam(':usrid', $usrid);
                        $stmt->bindParam(':date_deb_astreintes', $date_deb_astreintes);
                        $stmt->bindParam(':date_fin_astreintes', $date_fin_astreintes);
                        $stmt->bindParam(':annee', $year);
                        $stmt->execute();
                    } else {
                        $sql = "INSERT INTO k2_astreintes (usrid, date_deb_astreintes, date_fin_astreintes) VALUES (:usrid, :date_deb_astreintes, :date_fin_astreintes)";
                        $stmt = $cn->prepare($sql);
                        $stmt->bindParam(':usrid', $usrid);
                        $stmt->bindParam(':date_deb_astreintes', $date_deb_astreintes);
                        $stmt->bindParam(':date_fin_astreintes', $date_fin_astreintes);
                        $stmt->execute();
                    }


                    $dateCourante = date('Y-m-d');
                    $semaineCourante = date('W', strtotime($dateCourante));

                    if ($semaineCourante == $datesSemaine) {
                        $sql = "UPDATE k2users SET astreinte = 1 WHERE astreinte = 2";
                        $stmt = $cn->prepare($sql);
                        $stmt->execute();

                        $sql = "UPDATE k2users SET astreinte = 2 WHERE usrid = :usrid";
                        $stmt = $cn->prepare($sql);
                        $stmt->bindParam(':usrid', $usrid);
                        $stmt->execute();
                    }
                } catch (Exception $e) {
                    var_dump($e);
                }
            }
        }
        try {
            $dateCourante = date('Y-m-d');
            $semaineCourante = date('W', strtotime($dateCourante));
            $sql = "UPDATE k2users SET astreinte = 1 WHERE (astreinte = 2 AND WEEK(date_deb_astreintes) < :semaineCourante))";
            $stmt = $cn->prepare($sql);
            $stmt->bindParam(':semaineCourante', $semaineCourante);
            $stmt->execute();
        } catch (Exception $e) {
            var_dump($e);
        }
    }
    return astreintesFormulaire("Astreintes modifiées avec succès !");
}

// Obetenir la date de début et de fin d'une semaine d'une année donnée
function getWeekStartEndDates($week, $year){
    $dto = new DateTime();
    $dto->setISODate($year, $week);
    $date_deb_astreintes = $dto->format('Y-m-d');
    $dto->modify('+6 days');
    $date_fin_astreintes = $dto->format('Y-m-d');
    return array($date_deb_astreintes, $date_fin_astreintes);
}

// Exécute le changement d'astreinte en utilisant une tâche cron
function astreintesCron() {
    global $config;

    try {
        $user = $config['bdd_username'];
        $password = $config['bdd_password'];
        $server = $config['bdd_server'];
        $database = $config['bdd_database'];
        $cn = new PDO("mysql:host=$server;dbname=$database", $user, $password);
    } catch (Exception $e) {
        var_dump($e);
    }


    try {
        // Astreinte qui se termine : passer le flag de 2 à 1
        $currentDate = date('Y-m-d');
        $sql = "UPDATE k2users SET astreinte = 1 WHERE astreinte = 2";
        $stmt = $cn->prepare($sql);
        $stmt->execute();

        // Astreinte qui commence : passer le flag de 1 à 2 si la semaine est terminée

        $nextDate = date('Y-m-d', strtotime('+1 day'));
        $weekEndDate = date('Y-m-d', strtotime('next Sunday'));
        $finAstreinte = date('Y-m-d', strtotime('+2 week'));

        if ($currentDate >= $weekEndDate) {
            $sql = "SELECT k2users.usrid, k2users.email, k2users.nick, k2people.portable
                    FROM k2_astreintes 
                    INNER JOIN k2users ON k2_astreintes.usrid = k2users.usrid
                    INNER JOIN k2people ON k2users.kid_salaries = k2people.kid
                    WHERE date_deb_astreintes = :nextDate";
            $stmt = $cn->prepare($sql);
            $stmt->bindParam(':nextDate', $nextDate);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($result as $row) {

                file_put_contents("/opt/www/k2/tmp/astreinte.txt", '');

                $usrid = $row['usrid'];
                $email = $row['email'];
                $nom = $row['nick'];
                $mobile = $row['portable'];

                $sql = "UPDATE k2users SET astreinte = 2 WHERE usrid = :usrid";
                $stmt = $cn->prepare($sql);
                $stmt->bindParam(':usrid', $usrid);
                $stmt->execute();

                $subject = "Debut Astreinte";
                $message = "Bonjour,
                \nVous démarrez votre astreinte aujourd'hui. Votre astreinte se termine le $finAstreinte matin.
                \nCordialement,
                \nKyxar";
                $headers = "From: k2 Astreinte <robot@kyxar.fr>";

                mail($email, $subject, $message, $headers);

                $content = "nom=$nom\nmobile=$mobile\nemail=$email\n";
                file_put_contents("/opt/www/k2/tmp/astreinte.txt", $content, FILE_APPEND);

            }
        }
    } catch (Exception $e) {
        var_dump($e);
    }
}

// Affiche le formulaire pour les astreintes
function astreintesFormulaire($D, $Erreur = "")
{
    // Vérifier l'utilisateur connecté
    $utilisateursModifiables = ['rsi', 'twoki', 'etienne'];
    $modifiable = in_array($_SESSION['login'], $utilisateursModifiables);

    // Récupère l'année sélectionnée depuis l'URL
    $anneeSelectionnee = isset($_GET['annee']) ? intval($_GET['annee']) : date('Y');
    $moisCourant = date('n');
    $dateActuel = date('Ymd');
    $anneeActuel = date('Y');
    $anneeCourant = ($anneeSelectionnee != date('Y')) ? $anneeSelectionnee : date('Y');

    // Vérifie si une date est spécifiée dans l'URL
    $dateSpecifiee = isset($_GET['annee']) ? strtotime($_GET['annee']) : false;


    // Calcul des mois à afficher en fonction de la présence de la date spécifiée
    $moisDebut = $dateSpecifiee ? 1 : $moisCourant;
    $moisFin = $dateSpecifiee ? 12 : $moisCourant + 5;

    // Génère les liens de navigation sur les années d'astreintes
    $lienAstreinte = "?Action=astreintes&Etape=Nouv";
    $anneesHTML = '';
    $anneeMin = $anneeCourant - 5;
    $anneeMax = $anneeCourant + 5;
    for ($annee = $anneeMin; $annee <= $anneeMax; $annee++) {
        $active = ($annee == $anneeSelectionnee) ? 'active' : '';
        $lienAstreinteAvecAnnee = $lienAstreinte . "&annee=$annee";
        $anneesHTML .= "<a href='$lienAstreinteAvecAnnee' class='$active'>$annee</a>";
    }

    echo "<div class='navigation-annees'>$anneesHTML</div>";


    try
    {
        global $config;

        $user = $config['bdd_username'];
        $password = $config['bdd_password'];
        $server = $config['bdd_server'];
        $database = $config['bdd_database'];
        $cn = new PDO("mysql:host=$server;dbname=$database", $user, $password);

    }catch (Exception $e){var_dump($e);}


    $moiss = array();

    for ($i = $moisDebut; $i <= $moisFin; $i++) {
        $mois = $i;
        $annee = $anneeCourant;

        if ($mois > 12) {
            $mois -= 12;
            $annee++;
        }

        $moiss[] = array(
            'month' => $mois,
            'year'  => $annee
        );
    }

    $date_debut = new DateTime($moiss[0]['year'] . '-' . $moiss[0]['month'] . '-01');
    $date_fin = new DateTime($moiss[count($moiss) - 1]['year'] . '-' . $moiss[count($moiss) - 1]['month'] . '-01');
    $date_fin->modify('last day of this month');

    $sql = "select * from k2_astreintes where date_deb_astreintes >= :date_debut AND date_deb_astreintes <= :date_fin";
    $stmt = $cn->prepare($sql);
    $stmt->execute([
        'date_debut' => $date_debut->format('Y-m-d'),
        'date_fin' => $date_fin->format('Y-m-d')
    ]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $astreintes = array();
    foreach ($results as $result) {
        $astreintes[$result['date_deb_astreintes']] = $result['usrid'];
    }

    $moisHTML = '';
    $moisHTML .= "<table>";

    setlocale(LC_TIME, 'fr_FR.ISO8859-1');

    $joursSemaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $moisHTML .= "<tr><th>Mois</th><th>Semaine</th>";

    foreach ($joursSemaine as $jour) {
        $moisHTML .= "<th>$jour</th>";
    }

    $sql = "SELECT nom, usrid FROM k2users WHERE astreinte >='1' and actif='1' ORDER BY nom";
    $recup = $cn->query($sql);
    $results = $recup->fetchAll(PDO::FETCH_ASSOC);


    foreach ($results as $result) {
        $moisHTML .= "<th class='result'>{$result['nom']}</th>";
    }

    $moisHTML .= "</tr>";

    $debutLigne = true;
    $couleurFond = "yellow";

    $premierJour = new DateTime("{$moiss[0]['year']}-{$moiss[0]['month']}-01");
    $premiereLigne = $premierJour->format('W') >= 52;


    foreach ($moiss as $key => $moisData)
    {

        $mois = $moisData['month'];
        $annee = $moisData['year'];
        $premierJour = new DateTime("$annee-$mois-01");
        $premierJourMoisApres = new DateTime("$annee-$mois-01");
        $premierJourMoisApres->modify('first day of next month');

        $interval = new DateInterval('P1D');
        $periode = new DatePeriod($premierJour, $interval, $premierJourMoisApres);

        $nombreLundis = 0;

        foreach ($periode as $jour) {
            if ($key == 0 && $jour->format('j') == 1 && $jour->format('N') != 1) {
                $nombreLundis++;
            }

            if ($jour->format('N') == 1) {
                $nombreLundis++;

            }
        }
        $NbJours = cal_days_in_month(CAL_GREGORIAN, $mois, $annee);

        $premierJoursSemaine = date('N', strtotime("$annee-$mois-01"));

        $semaineCourante = date('W', strtotime("$annee-$mois-01"));
        $semaineCourante--;
        $compteurSemaine = $semaineCourante;


        $nomMois = strftime("%B", mktime(0, 0, 0, $mois, 1, $annee));
        $nomMois = wordwrap($nomMois, 1, '<br />', true);
        $celluleMoisHTML = "<td rowspan='" . $nombreLundis . "' class='mois'>$nomMois</td>";

        for ($jour = 1; $jour <= $NbJours; $jour++)
        {
            $semaine = date('W', strtotime("$annee-$mois-$jour"));
            if ($semaine != $semaineCourante)
            {
                $semaineCourante = $semaine;
                $compteurSemaine++;


                if ($debutLigne)
                {
                    $moisHTML .= "<tr>$celluleMoisHTML<td class='semaine-nbr'>$semaine</td>";
                    $celluleMoisHTML = '';
                    $debutLigne = false;

                }

                if ($jour == date('j') && $mois == date('n') && $annee == date('Y'))
                {

                }

            }

            if ($key == 0 && $jour == 1)
            {
                for ($i = 1; $i < $premierJoursSemaine; $i++)
                {
                    $moisHTML .= "<td class='vide'></td>";
                }
            }

            $moisHTML .= "<td class='$couleurFond'>$jour</td>";

            if (($key == count($moiss) - 1 && $jour == $NbJours && ($jour + $premierJoursSemaine - 1) % 7 != 0))
            {
                for ($i = ($jour + $premierJoursSemaine - 1) % 7; $i < 7; $i++)
                {
                    $moisHTML .= "<td class='vide'></td>";
                }
            }

            if (($jour + $premierJoursSemaine - 1) % 7 == 0 || ($key == count($moiss) - 1 && $jour == $NbJours)) {

                foreach ($results as $result) {
                    $moisHTML .= "<td>";
                    $usrid = $result['usrid'];
                    $checked = '';
                    list($date_deb_astreintes) = getWeekStartEndDates($semaine, $annee);
                    if ($astreintes[$date_deb_astreintes] == $usrid) {
                        $checked = 'checked';
                    }

                    if ($premiereLigne) {
                        $moisHTML .= "<input type='radio' name='astreinte_{$semaine}_{$annee}' value='{$result['usrid']}' $checked disabled>";
                    } else {
                        if ($modifiable || $annee > $anneeActuel || ($annee == $anneeActuel && str_replace('-', '', $date_deb_astreintes) >= $dateActuel)) {
                            $moisHTML .= "<input type='radio' name='astreinte_{$semaine}_{$annee}' value='{$result['usrid']}' $checked>";
                        } else {
                            $moisHTML .= "<input type='radio' name='astreinte_{$semaine}_{$annee}' value='{$result['usrid']}' $checked disabled>";
                        }
                    }

                    $moisHTML .= "</td>";
                }

                if ($premiereLigne) {
                    $premiereLigne = false;
                }

                $moisHTML .= "</tr>";
                $debutLigne = true;
            }

        }

        $couleurFond = ($couleurFond == "yellow") ? "verte" : "yellow";
    }

    $moisHTML .= "</tr>";
    $moisHTML .= "</table>";
    $moisHTML .= "<br>";



    $T = 0;
    $H = ToHTML($T);

    $HTML = <<<END
    <form ACTION='$_SERVER[PHP_SELF]' METHOD=POST>
    <input TYPE=HIDDEN NAME=Action VALUE='$_REQUEST[Action]'>
    <input TYPE=HIDDEN NAME=Etape  VALUE='Edit'>
    <input TYPE="HIDDEN" NAME="annee" VALUE="$anneeCourant">
    $H

    <div class='calendrier'>$moisHTML</div>
    <br>
    <br>
    <input type='submit' name='enregistrer' value='Enregistrer'>

    </form>
<style>
.calendrier {
    font-family: Arial, sans-serif;
    border-collapse: collapse;
    width: 100%;
    margin-right: 10px;
    table-layout: fixed;
    width: 100%;
}
.calendrier th:first-child,
.calendrier td:first-child {
    width: 4%;
}
.calendrier th,
.calendrier td {
    width: 3%;
}
.calendrier th:last-child,
.calendrier td:last-child {
    width: 5%;
}
.calendrier th {
    background-color: #f2f2f2;
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

.calendrier td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: center;
}

.calendrier .mois {
    font-weight: bold;
    background-color: #f2f2f2;
}

.calendrier .semaine-nbr {
    margin-right: 10px;
    background-color: #f2f2f2;
}

.calendrier .yellow {
    background-color: rgba(255,240,171,0.6);
}

.calendrier .verte {
    background-color: rgba(54,110,37,0.6);
}
.calendrier td.vide {
    background-color: #f2f2f2;
}
.calendrier .result {
    background-color: #f2f2f2;
    text-align: center;
    vertical-align: middle;
}
label {
    display: block;
    margin-bottom: 5px;
}
.navigation-annees {
  margin-bottom: 20px;
  text-align: center;
}
.navigation-annees a.active {
  background-color: rgba(54,110,37,0.6);
  color: rgba(255,240,171);
}
.navigation-annees a {
  display: inline-block;
  margin-right: 10px;
  padding: 5px 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
  color: #333;
  text-decoration: none;
}
</style>
END;

    return $HTML;
}
dsfdsf