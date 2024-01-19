<?php
use setasign\Fpdi\Fpdi;
require('fpdf/fpdf.php');
require_once('fpdi/autoload.php');
$dbname = "livret";
$dbuser = "root";
$dbpassword = "IIA_44";
$dbip = "localhost";
$bdd = new PDO("mysql:host=" . $dbip . ";dbname=" . $dbname . ";charset=utf8", $dbuser, $dbpassword);
//ici
class PDF extends FPDF
{
    function Footer()
{
    $this->SetTextColor(0,0,0);
    // Positionnement à 1,5 cm du bas
    $this->SetY(-15);
    // Police Arial italique 8
    $this->SetFont('Arial','I',8);
    // Numéro de page
    $this->Cell(0,10,utf8_decode("LIVRET D'APPRENTISSAGE / BTS SIO SISR"));
}

protected $B = 0;
protected $I = 0;
protected $U = 0;
protected $HREF = '';
protected $widths;
protected $aligns;

function WriteHTML($html)
{
    // Parseur HTML
    $html = str_replace("\n",' ',$html);
    $a = preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
    foreach($a as $i=>$e)
    {
        if($i%2==0)
        {
            // Texte
            if($this->HREF)
                $this->PutLink($this->HREF,$e);
            else
                $this->Write(5,$e);
        }
        else
        {
            // Balise
            if($e[0]=='/')
                $this->CloseTag(strtoupper(substr($e,1)));
            else
            {
                // Extraction des attributs
                $a2 = explode(' ',$e);
                $tag = strtoupper(array_shift($a2));
                $attr = array();
                foreach($a2 as $v)
                {
                    if(preg_match('/([^=]*)=["\']?([^"\']*)/',$v,$a3))
                        $attr[strtoupper($a3[1])] = $a3[2];
                }
                $this->OpenTag($tag,$attr);
            }
        }
    }
}

function OpenTag($tag, $attr)
{
    // Balise ouvrante
    if($tag=='B' || $tag=='I' || $tag=='U')
        $this->SetStyle($tag,true);
    if($tag=='A')
        $this->HREF = $attr['HREF'];
    if($tag=='BR')
        $this->Ln(5);
}

function CloseTag($tag)
{
    // Balise fermante
    if($tag=='B' || $tag=='I' || $tag=='U')
        $this->SetStyle($tag,false);
    if($tag=='A')
        $this->HREF = '';
}

function SetStyle($tag, $enable)
{
    // Modifie le style et sélectionne la police correspondante
    $this->$tag += ($enable ? 1 : -1);
    $style = '';
    foreach(array('B', 'I', 'U') as $s)
    {
        if($this->$s>0)
            $style .= $s;
    }
    $this->SetFont('',$style);
}

function PutLink($URL, $txt)
{
    // Place un hyperlien
    $this->SetTextColor(0,0,255);
    $this->SetStyle('U',true);
    $this->Write(5,$txt,$URL);
    $this->SetStyle('U',false);
    $this->SetTextColor(0);
}

//Angles arrondis 

function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));

        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }



//Tableaux MutiCell

function SetWidths($w)
    {
        // Set the array of column widths
        $this->widths = $w;
    }

    function SetAligns($a)
    {
        // Set the array of column alignments
        $this->aligns = $a;
    }

    function Row($data)
    {
        // Calculate the height of the row
        $nb = 0;
        for($i=0;$i<count($data);$i++)
            $nb = max($nb,$this->NbLines($this->widths[$i],$data[$i]));
        $h = 5*$nb;
        // Issue a page break first if needed
        $this->CheckPageBreak($h);
        // Draw the cells of the row
        for($i=0;$i<count($data);$i++)
        {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            // Save the current position
            $x = $this->GetX();
            $y = $this->GetY();
            // Draw the border
            $this->Rect($x,$y,$w,$h);
            // Print the text
            $this->MultiCell($w,5,$data[$i],0,$a);
            // Put the position to the right of the cell
            $this->SetXY($x+$w,$y);
        }
        // Go to the next line
        $this->Ln($h);
    }

    function CheckPageBreak($h)
    {
        // If the height h would cause an overflow, add a new page immediately
        if($this->GetY()+$h>$this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }

    function NbLines($w, $txt)
    {
        // Compute the number of lines a MultiCell of width w will take
        if(!isset($this->CurrentFont))
            $this->Error('No font has been set');
        $cw = $this->CurrentFont['cw'];
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
        $s = str_replace("\r",'',(string)$txt);
        $nb = strlen($s);
        if($nb>0 && $s[$nb-1]=="\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while($i<$nb)
        {
            $c = $s[$i];
            if($c=="\n")
            {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if($c==' ')
                $sep = $i;
            $l += $cw[$c];
            if($l>$wmax)
            {
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                }
                else
                    $i = $sep+1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            }
            else
                $i++;
        }
        return $nl;
    }
}
$lucie = utf8_decode('La CCI de la Mayenne poursuit le développement de sa politique RSE à travers 10 engagements à mettre en oeuvre sur le cycle de labellisation de 3 ans. Retrouvez ces engagements sur <a href="https://www.mayenne.cci.fr/votre-cci/decouvrez-la-cci-de-la-mayenne/cci-responsable">https://www.mayenne.cci.fr/votre-cci/decouvrez-la-cci-de-la-mayenne/cci-responsable</a>.');

$prevention1 = utf8_decode('<a href="http://www.inrs.fr/risques/electriques/prevention-risque-electrique.html">http://www.inrs.fr/risques/electriques/prevention-risque-electrique.html</a>');

$prevention2 = utf8_decode('<a href="https://travail-emploi.gouv.fr/droit-du-travail/egalite-professionnelle-discrimination-et-harcelement/article/le-harcelement-moral#Qui-organise-la-prevention-en-matiere-de-harcelement-moral">https://travail-emploi.gouv.fr/droit-du-travail/egalite-professionnelle-discrimination-et-harcelement/article/le-harcelement-moral#Qui-organise-la-prevention-en-matiere-de-harcelement-moral</a>');

$prevention3 = utf8_decode("<b>et ne pas tout accepter : </b> Au travail, comme ailleurs, certains comporteents ne sont pas acceptables : agression verbale, malveillance, insultes, contraintes physique ou à caractère sexuel, humiliation... Ces comportements doivent inciter à alerter.");

$DLaurent = utf8_decode("    <b>David laurent</b>, Directeur de CCI Formation");

$FMorin = utf8_decode("    <b>Françoise Morin</b>, Responsable d'établissement Campus/IIA");

$JTaburet = utf8_decode("    <b>Julien TABURET</b>, Chargé des relations commerciales en formation");

$SHay = utf8_decode("    <b>Sylvia HAY</b>, Assistante dédiée à la pédagogie");

$BDolley = utf8_decode("    <b>Brigitte DOLLEY</b>, Assistante dédiée à l'administration");

$AMartineau = utf8_decode("    <b>Aurélien MARTINEAU</b>, Responsable pédagogique IIA");

$CMArtin = utf8_decode("    <b>Cécile MARTIN</b>, Coordinatrice pédagogique IIA et référente de classe BTSA 2ème année");

$DBristol = utf8_decode("    <b>David BRISTOL</b>, Formateur Responsable de Classe BTSA 1ère année");

$PFoucault = utf8_decode("    <b>Pauline FOUCAULT</b>, Accompagnatrice vie sociale et professionnelle");

$EMarsat = utf8_decode("    <b>Estelle MARSAT</b>, Référente handicap");

$Ypareo = utf8_decode('    <b>Net-Yparéo</b> : <a href="https://formations.mayenne.cci.fr">https://formations.mayenne.cci.fr</a>');

$dd = utf8_decode("<b>Le salarié en contrat d'apprentissage ou contrat de professionnalisation s'engage à :</b>");

$dd2 = utf8_decode("<b>L'employeur s'engage à :</b>");

$dd3 = utf8_decode("<b>Le CFA s'engage à :</b>");

$dd4 = utf8_decode('<b>Pour plus d informations : consulter notre rubrique contrats du site <a href="https://www.cciformation53.fr/">https://www.cciformation53.fr/</a> ou le site </b>');

$dd5 = utf8_decode('<b><a href="https://www.service-public.fr/particuliers/vosdroits/N11240">service-public.fr.</a></b>');

$dd6 = utf8_decode("<b>Vous trouverez également sur notre site internet formation des informations concernant le médiateur de l'apprentissage :</b>");

$dd7 = utf8_decode('<b><a href="https://www.cciformation53.fr/mediateur-de-lapprentissage/">https://www.cciformation53.fr/mediateur-de-lapprentissage/</a></b>');


// Instanciation de la classe dérivée
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','B',15);
// Décalage à droite
$pdf->Cell(5);
// Couleur de remplissage
$pdf->SetFillColor(55, 61, 245) ;
$pdf->SetTextColor(255,255,255);
$pdf->Image('images/img0.png',20,20,170);
// Saut de ligne
$pdf->Ln(70);
$pdf->SetFont('Arial','B',35);
$pdf->SetTextColor(55, 61, 245) ;
$pdf->cell(25);
$pdf->Cell(40,10,utf8_decode("Livret d'apprentissage"));
$pdf->Ln(30);
$pdf->SetFont('Arial','',27);
$pdf->SetTextColor(55, 61, 245) ;
$pdf->cell(25);
$pdf->Cell(40,10,utf8_decode("BTS Services Informatique aux"));
$pdf->Ln(15);
$pdf->cell(38);
$pdf->SetFont('Arial','',27);
$pdf->SetTextColor(55, 61, 245) ;
$pdf->cell(25);
$pdf->Cell(40,10,utf8_decode("Organisations"));
$pdf->Ln(40);
$pdf->Image('images/img1.png',35,145,130);
$pdf->Ln(50);
$pdf->SetFont('Arial','',13);
$pdf->SetTextColor(55, 61, 245) ;
$pdf->cell(25);
$pdf->Cell(40,10,utf8_decode("Nom :   ............................"));
$pdf->Ln(10);
$pdf->SetFont('Arial','',13);
$pdf->SetTextColor(55, 61, 245) ;
$pdf->cell(25);
$pdf->Cell(40,10,utf8_decode("Prénom :   ............................"));
$pdf->Ln(10);
$pdf->SetFont('Arial','',13);
$pdf->SetTextColor(55, 61, 245) ;
$pdf->cell(25);
$pdf->Cell(40,10,utf8_decode("Groupe : BTS SIO SISR - A1"));
$pdf->Ln(10);
$pdf->Output("F","documentsPdf/accueil.pdf");
////////////////// FIN PAGE ACCUEIL /////////////////////
//finici
// Instanciation de la classe dérivée
$pdfi = new Fpdi();
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/accueil.pdf");
// import page 1
$tplId = $pdfi->importPage(1);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
// add a page
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reglesDeVie.pdf");
// import page 1
$tplId = $pdfi->importPage(1);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reglesDeVie.pdf");
// import page 1
$tplId = $pdfi->importPage(2);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
////////////////// FIN REGLES DE VIE /////////////////////
////////////////// DEBUT DROITS ET DEVOIRS ///////////////
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/droitsEtDevoirs.pdf");
// import page 1
$tplId = $pdfi->importPage(1);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
////////////////// FIN DROITS ET DEVOIRS /////////////////////
////////////////// DEBUT LABEL LUCIE /////////////////////////
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/labelLucie.pdf");
// import page 1
$tplId = $pdfi->importPage(1);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
////////////////// FIN LABEL LUCIE /////////////////////
////////////////// DEBUT ECO CITOYEN ///////////////////
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/comportementEcoCitoyen.pdf");
// import page 1
$tplId = $pdfi->importPage(1);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
////////////////// FIN ECO CITOYEN /////////////////////
////////////////// DEBUT HYGIENE ET SECURITE ///////////
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/hygieneEtSecurite.pdf");
// import page 1
$tplId = $pdfi->importPage(1);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
////////////////// FIN HYGIENE ET SECURITE /////////////////////
////////////////// DEBUT REGLEMENT INTERIEUR  //////////////////
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/PR-Reglement Interieur de l'Organisme de Formation de la CCI53-v15.05.2023 (1).pdf");
// import page 1
$tplId = $pdfi->importPage(1);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/PR-Reglement Interieur de l'Organisme de Formation de la CCI53-v15.05.2023 (1).pdf");
// import page 1
$tplId = $pdfi->importPage(2);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/PR-Reglement Interieur de l'Organisme de Formation de la CCI53-v15.05.2023 (1).pdf");
// import page 1
$tplId = $pdfi->importPage(3);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/PR-Reglement Interieur de l'Organisme de Formation de la CCI53-v15.05.2023 (1).pdf");
// import page 1
$tplId = $pdfi->importPage(4);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/PR-Reglement Interieur de l'Organisme de Formation de la CCI53-v15.05.2023 (1).pdf");
// import page 1
$tplId = $pdfi->importPage(5);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/PR-Reglement Interieur de l'Organisme de Formation de la CCI53-v15.05.2023 (1).pdf");
// import page 1
$tplId = $pdfi->importPage(6);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/PR-Reglement Interieur de l'Organisme de Formation de la CCI53-v15.05.2023 (1).pdf");
// import page 1
$tplId = $pdfi->importPage(7);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/PR-Reglement Interieur de l'Organisme de Formation de la CCI53-v15.05.2023 (1).pdf");
// import page 1
$tplId = $pdfi->importPage(8);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/PR-Reglement Interieur de l'Organisme de Formation de la CCI53-v15.05.2023 (1).pdf");
// import page 1
$tplId = $pdfi->importPage(9);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdf = new PDF();
$pdf->AddPage();
$pdf->SetDrawColor(0,0,0);
$pdf->SetLineWidth(0);
$pdf->SetFillColor(255,255,255);
$pdf->SetTextColor(51, 51, 255);
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode('PREVENTION DES RISQUES PROFESSIONNELS'),1,1,'C', 'true');
$pdf->Ln(5);
$pdf->SetFillColor(255,0,0);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(97,5,utf8_decode('Vous êtes exposés à des troubles musculosquelettiques (TMS)'),0,1,'', 'true');
$pdf->Ln(1);
$pdf->SetTextColor(51, 51, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(180,5,utf8_decode('PREVENTION'));
$pdf->Ln(5);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Pauses suffisantes"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Sièges réglables (Les pieds doivent reposer à plat sur le sol ou sur un repose-pied ; dos droit ou légèrement en arrière, et soutenu par le dossier) "));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Clavier et tapis de souris ergonomiques et positionnement adéquat du bras et la main (angle du coude droit ou légèrement obtus ; avant-bras proches du corps ; main dans le prolongement de l'avant-bras) "));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Espace suffisant pour travailler "));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Ergonomie du poste de travail sur écran : écran orientable en hauteur et latéralement. "));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Formation-sensibilisation des salariés aux postures de travail sur écran "));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Utiliser les moyens d'aide à la manutention (diable, chariot...)"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Veiller à une hydratation régulière"));
$pdf->Ln(5);
$pdf->SetFillColor(255,0,0);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(68,5,utf8_decode("Vous êtes exposés au stress, à l'agressivité"),0,1,'', 'true');
$pdf->Ln(1);
$pdf->SetTextColor(51, 51, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(180,5,utf8_decode('PREVENTION'));
$pdf->Ln(5);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Signaler les incidents et dysfonctionnements à l'employeur"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Connaître sa fiche de poste et les procédures de travail"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Parler de vos difficultés, ne pas hésiter à demander conseil (responsables, collègues, médecin du travail...)"));
$pdf->Ln(5);
$pdf->SetFillColor(255,0,0);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(80,5,utf8_decode("Vous êtes exposés à la gêne ou à la fatigue visuelle"),0,1,'', 'true');
$pdf->Ln(1);
$pdf->SetTextColor(51, 51, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(180,5,utf8_decode('PREVENTION'));
$pdf->Ln(5);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Utiliser les dispositifs d'éclairage (plafonner, lampe d'appoint...) pour maintenir un éclairage de qualité, homogène, adapté à la tâche à réaliser"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Signaler immédiatement les luminaires défectueux et les zones mal éclairées"));
$pdf->Ln(5);
$pdf->SetFillColor(255,0,0);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(77,5,utf8_decode("Vous pouvez être exposés à l'inconfort thermique"),0,1,'', 'true');
$pdf->Ln(1);
$pdf->SetTextColor(51, 51, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(180,5,utf8_decode('PREVENTION'));
$pdf->Ln(5);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Signaler les inconforts ressentis (courants d'air, sensations de froid, de chaleur excessive...)"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Utiliser les appareils de régulation thermique (chauffage, climatisation)"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Adapter sa tenue de travail"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Bien s'hydrater au cours de la journée"));
$pdf->Ln(5);
$pdf->SetFillColor(255,0,0);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(77,5,utf8_decode("Vous êtes exposés au risque électrique"),0,1,'', 'true');
$pdf->Ln(1);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(10);
$pdf->MultiCell(180,5,utf8_decode("Utilisation en sécurité des matériels et installations 
Le matériel électrique doit toujours être utilisé avec soin, en veillant à ne pas le détériorer par des chocs, une immersion, un échauffement excessif... Le salarié utilisant ce matériel doit respecter les consignes fournies par son employeur. Il est tenu d'en vérifier l'état et de signaler toute détérioration à son encadrement."));
$pdf->Ln(2);
$pdf->SetTextColor(51, 51, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(180,5,utf8_decode('PREVENTION'));
$pdf->Ln(5);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Protéger les fils conducteurs du risque d'écrasement en ne les déroulant pas en travers du passage d'un véhicule "));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Débrancher les appareils en tirant sur la fiche et non sur le fil"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Ne jamais bricoler une prise électrique endommagée"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Ne jamais laisser une rallonge branchée à une prise sans qu'elle soit reliée à un appareil électrique"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Ne jamais utiliser un fil pour tirer ou déplacer un appareil électrique"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Ne jamais toucher à un fil dénudé dont on perçoit qu'une extrémité"));
$pdf->Ln(1);
$pdf->Cell(15);
$pdf->MultiCell(170,5,utf8_decode("- Ne jamais toucher une prise avec les mains mouillées"));
$pdf->Ln(5);
$pdf->SetFillColor(255,0,0);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(77,5,utf8_decode("Vous êtes exposés au harcèlement "),0,1,'', 'true');
$pdf->Ln(1);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(10);
$pdf->MultiCell(180,5,utf8_decode("Défini par le Code du travail, le harcèlement moral se manifeste par des agissements répétés qui ont pour objet ou pour effet une dégradation des conditions de travail susceptible de porter atteinte aux droits de la personne du salarié au travail et à sa dignité, d'altérer sa santé physique ou mentale ou de compromettre son avenir professionnel. Son auteur : un employeur, un collègue de la victime, quelle que soit sa position hiérarchique..."));
$pdf->Ln(2);
$pdf->SetTextColor(51, 51, 255);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(10);
$pdf->Cell(180,5,utf8_decode('PREVENTION'));
$pdf->Ln(5);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode("L'employeur est tenu envers ses salariés d'une obligation de sécurité de résultat en matière de protection de la santé et de la sécurité des travailleurs dans l'entreprise, notamment en matière de harcèlement moral. Il doit prendre toutes dispositions nécessaires en vue de prévenir les agissements de harcèlement moral. Il a, pour cela, une totale liberté dans le choix des moyens à mettre en oeuvre."));
$pdf->Ln(10);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(10);
$pdf->Cell(180,5,utf8_decode("Sources : "));
$pdf->Ln(10);
$pdf->WriteHTML($prevention1);
$pdf->WriteHTML($prevention2);
$pdf->Ln(25);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("RÈGLES APPLICABLES EN MATIÈRE D'HYGIÈNE ET DE SÉCURITÉ EN MILIEU PROFESSIONNEL"));
$pdf->Ln(5);
$posX = $pdf->getX();
$posX = $posX + 6;
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Ln(5);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("L'employeur doit : "));
$pdf->Ln(5);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". Evaluer les risques pour la santé et a sécurité de ses salariés"));
$pdf->Ln(1);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". Mettre en oevre des actions de préventions"));
$pdf->Ln(1);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". Privilégier la mise en place de protections collectives (ex : garde corps, aspiration de poussière de bois, ouverture impossible de pétrin pendant son fonctionnement...)"));
$pdf->Ln(1);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". Mettre à disposition les équipements de protection individuelle (EPI) nécessaires (ex : masque respiratoire, gants, casque de chantier, bouchons d'oreille, lunettes de protection...). Ces EPI ainsi que tout autre vêtement de travail sont mis gratuitement à disposition des salariés. Leur entretien et leur renouvellement sont à la charge de l'employeur."));
$pdf->Ln(1);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". Former et informer sur les risques pour la santé et la sécurité, et les mesures prises pour y remédier"));
$pdf->Ln(5);
$pdf->SetFont('Arial','B',9);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("L'alternant doit adopter les bonnes pratiques : "));
$pdf->Ln(5);
$pdf->SetTextColor(0,0,0);
$pdf->SetFont('Arial','',9);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". Respecter les règles et consignes de sécurité"));
$pdf->Ln(1);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". Adopter les bonnes postures de travail"));
$pdf->Ln(1);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". Ne pas hésiter à s'étirer avant de réaliser une tâche physique"));
$pdf->Ln(1);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". Utiliser des équipements de protections collectives et porter ses équipements ed protection individuelle (EPI)..."));
$pdf->Ln(1);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". S'assurer d'avoir bien compris ce qu'on lui demande"));
$pdf->Ln(1);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". Ne pas hésiter à poser des questions"));
$pdf->Ln(1);
$pdf->Cell(10);
$pdf->MultiCell(170,5,utf8_decode(". Parler de ses difficultés : faire remonter dans l'entreprise tout manquement qui pourrait le mettre en danger"));
$pdf->Ln(5);
$pdf->WriteHTML($prevention3);
////////////////// FIN PREVENTION RISQUES /////////////////////
////////////////// DEBUT INFORMATIONS  ////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->Cell(70);
$pdf->Cell(180,10,utf8_decode('INFORMATIONS'));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',11);
$pdf->WriteHTML($DLaurent);
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("02 43 91 47 47 - laurent.david@mayenne.fr"));
$pdf->Ln(10);
$pdf->WriteHTML($FMorin);
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("02 43 91 47 46 - francoise.morin@mayenne.cci.fr"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode('RELATION ÉCOLE/ENTREPRISE'));
$pdf->Ln(8);
$pdf->SetFont('Arial','B',11);
$pdf->WriteHTML($JTaburet);
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("06 84 60 86 68 - Julien.taburet@mayenne.cci.fr"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode('ADMINISTRATIF'));
$pdf->Ln(8);
$pdf->SetFont('Arial','B',11);
$pdf->WriteHTML($SHay);
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("02 43 91 47 47 - sylvia.hay@mayenne.cci.fr"));
$pdf->Ln(10);
$pdf->WriteHTML($BDolley);
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("02 43 91 47 47 - brigitte.dolley@mayenne.cci.fr"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode('PEDAGOGIE'));
$pdf->Ln(8);
$pdf->SetFont('Arial','B',11);
$pdf->WriteHTML($AMartineau);
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("02 43 91 47 51 - aurelien.martineau@mayenne.cci.fr"));
$pdf->Ln(10);
$pdf->WriteHTML($CMArtin);
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("02 43 91 47 46 - cecile.martin@mayenne.cci.fr"));
$pdf->Ln(10);
$pdf->WriteHTML($DBristol);
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("david.bristol@iia-formation.fr"));
$pdf->Ln(10);
$pdf->WriteHTML($PFoucault);
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("02 43 91 47 56 - pauline.foucault@mayenne.cci.fr"));
$pdf->Ln(10);
$pdf->WriteHTML($EMarsat);
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("02 43 91 47 44 - estelle.marsat@mayenne.cci.fr"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode('PLANNINGS, NOTES ET REFERENTIELS'));
$pdf->Ln(8);
$pdf->SetFont('Arial','B',11);
$pdf->WriteHTML($Ypareo);
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("Portail web dédié au parcours de l'étudiant (accès plannings, notes, référentiels, etc.)"));
$pdf->Ln(5);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("Identifiants : communiqués par mail"));
$pdf->Ln(10);
////////////////// FIN INFORMATIONS /////////////////////
////////////////// DEBUT BLOCS COMPETENCES  /////////////
$pdf->AddPage();
////////////////// FIN BLOCS COMPETENCES /////////////////////
////////////////// DEBUT REGLEMENT EXAMENS  //////////////////
$pdf->AddPage();
////////////////// FIN REGLEMENT EXAMENS /////////////////////
////////////////// DEBUT E5  /////////////////////////////////
$pdf->AddPage();
////////////////// FIN E5 ///////////////////////////////////////////////
////////////////// DEBUT TRANSITION A1 //////////////////////////////////
$pdf->AddPage();
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$posX3 = $posX;
$posY3 = $posY + 260;
$posX4 = $posX2;
$posY4 = $posY2 + 260;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->SetFont('Arial','B',36);
$pdf->SetTextColor(68,72,230);
$pdf->Ln(110);
$pdf->Cell(35);
$pdf->MultiCell(130,5,utf8_decode("PREMIÈRE ANNÉE"));
$pdf->Ln(20);
$pdf->Cell(31);
$pdf->MultiCell(130,5,utf8_decode("D'APPRENTISSAGE"));
$pdf->Ln(40);
$pdf->Cell(77);
$pdf->SetFont('Arial','B',15);
$pdf->MultiCell(130,5,utf8_decode("2023 - 2024"));
////////////////// FIN TRANSITION A1 ////////////////////////////////////////////
////////////////// DEBUT EQUIPE PEDAGOGIQUE A1 //////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->SetTextColor(58,58,240);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("EQUIPE PEDAGOGIQUE - 1ERE ANNEE"));
$pdf->SetTextColor(0,0,0);
$pdf->Ln(15);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$posX6 = $posX2 / 2 + 20;
$posY6 = $posY2;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("MATIERE"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("Formateur"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Analyse et Méthodologie"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("BEUREL Jacky"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Anglais"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("BRISTOL David"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Ateliers de professionnalisation"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("BRISTOL David"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Bases de données"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("CADI TAZI Tawfiq"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Langage C"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("CADI TAZI Tawfiq"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Administrateur Linux"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("CHESNEAU Clément"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("CEJM"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("GUYARD Louis-Jonathan"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Intégration"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("HOUDAYER  Arnaud"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Enjeux climatiques et environnementaux orientés"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("JANSSENS Céline"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Algorithmie appliquée "));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("JEHAN Pierre"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("HTML / CSS / Boostrap"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("JEHAN Pierre"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Administration windows"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("LE ROI Florian"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Architecture réseaux bases des réseaux"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("LE ROI Florian"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Protection des données et de l'identité"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("LOUAISIL Maryse"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Mathématiques pour l'informatique"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("MESSOUDI Younss"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Introductin ITIL"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("PAMISEUX Marc-Henri"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Outil versionning"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("PEREIRA Damien"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Référencement / SEO"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("PEREIRA Damien"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Culture générale et expression"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("PONTGELARD Roselyne"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Administration système"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("ROTA Alexandre"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Exploitation des services"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("ROTA Alexandre"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Sécurité du SI"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("ROTA Alexandre"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Virtualisation"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("ROTA Alexandre"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN EQUIPE PEDAGOGIQUE A1 ///////////////////////////////////
////////////////// DEBUT COMPTES RENDUS S-O A1 /////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->SetTextColor(0,0,0);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Nom - Prénom : ..........................................................."));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Septembre / Octobre 2023"));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Compte-rendu d'activités en Entreprise"));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->MultiCell(180,5,utf8_decode('Activités professionnelles confiées en Entreprise'));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode('Missions, définitions et avancées des objectifs fixés, progrès en entreprise..., etc.'));
$pdf->Ln(50);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations de l'apprenti"));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->MultiCell(180,5,utf8_decode('Principales découvertes, difficultés de compréhension, liens entre les connaissances et les activités en entreprise, etc.'));
$pdf->Ln(40);
$pdf->Cell(160);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du tuteur/maître d'apprentissage"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du référent de groupe"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN COMPTES RENDUS S-O A1 ///////////////////////////////////
////////////////// DEBUT COMPTES RENDUS N-D A1 /////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Nom - Prénom : ..........................................................."));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Novembre / Décembre 2023"));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Compte-rendu d'activités en Entreprise"));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->MultiCell(180,5,utf8_decode('Activités professionnelles confiées en Entreprise'));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode('Missions, définitions et avancées des objectifs fixés, progrès en entreprise..., etc.'));
$pdf->Ln(50);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations de l'apprenti"));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->MultiCell(180,5,utf8_decode('Principales découvertes, difficultés de compréhension, liens entre les connaissances et les activités en entreprise, etc.'));
$pdf->Ln(40);
$pdf->Cell(160);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du tuteur/maître d'apprentissage"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du référent de groupe"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN COMPTES RENDUS N-D A1 ///////////////////////////////////
////////////////// DEBUT COMPTES RENDUS J-F A1 /////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Nom - Prénom : ..........................................................."));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Janvier / Février 2024"));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Compte-rendu d'activités en Entreprise"));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->MultiCell(180,5,utf8_decode('Activités professionnelles confiées en Entreprise'));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode('Missions, définitions et avancées des objectifs fixés, progrès en entreprise..., etc.'));
$pdf->Ln(50);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations de l'apprenti"));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->MultiCell(180,5,utf8_decode('Principales découvertes, difficultés de compréhension, liens entre les connaissances et les activités en entreprise, etc.'));
$pdf->Ln(40);
$pdf->Cell(160);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du tuteur/maître d'apprentissage"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du référent de groupe"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN COMPTES RENDUS J-F A1 ///////////////////////////////////
////////////////// DEBUT COMPTES RENDUS M-A A1 /////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Nom - Prénom : ..........................................................."));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Mars / Avril 2024"));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Compte-rendu d'activités en Entreprise"));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->MultiCell(180,5,utf8_decode('Activités professionnelles confiées en Entreprise'));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode('Missions, définitions et avancées des objectifs fixés, progrès en entreprise..., etc.'));
$pdf->Ln(50);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations de l'apprenti"));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->MultiCell(180,5,utf8_decode('Principales découvertes, difficultés de compréhension, liens entre les connaissances et les activités en entreprise, etc.'));
$pdf->Ln(40);
$pdf->Cell(160);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du tuteur/maître d'apprentissage"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du référent de groupe"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN COMPTES RENDUS M-A A1 ///////////////////////////////////
////////////////// DEBUT COMPTES RENDUS M-J A1 /////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Nom - Prénom : ..........................................................."));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Mai / Juin 2024"));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Compte-rendu d'activités en Entreprise"));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->MultiCell(180,5,utf8_decode('Activités professionnelles confiées en Entreprise'));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode('Missions, définitions et avancées des objectifs fixés, progrès en entreprise..., etc.'));
$pdf->Ln(50);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations de l'apprenti"));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->MultiCell(180,5,utf8_decode('Principales découvertes, difficultés de compréhension, liens entre les connaissances et les activités en entreprise, etc.'));
$pdf->Ln(40);
$pdf->Cell(160);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du tuteur/maître d'apprentissage"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du référent de groupe"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN COMPTES RENDUS M-J A1 ///////////////////////////////////
////////////////// DEBUT COMPTES RENDUS J-A A1 /////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Nom - Prénom : ..........................................................."));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Juillet / Août 2024"));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Compte-rendu d'activités en Entreprise"));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->MultiCell(180,5,utf8_decode('Activités professionnelles confiées en Entreprise'));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode('Missions, définitions et avancées des objectifs fixés, progrès en entreprise..., etc.'));
$pdf->Ln(50);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations de l'apprenti"));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->MultiCell(180,5,utf8_decode('Principales découvertes, difficultés de compréhension, liens entre les connaissances et les activités en entreprise, etc.'));
$pdf->Ln(40);
$pdf->Cell(160);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du tuteur/maître d'apprentissage"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du référent de groupe"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN COMPTES RENDUS J-A A1 ///////////////////////////////////
////////////////// DEBUT FICHE OBSERVATION A1 //////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->SettextColor(59,62,239);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("OBSERVATIONS DE L'APPRENTI"));
$pdf->Ln(10);
$pdf->SettextColor(0,0,0);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->SetFont('Arial','B',13);
$pdf->SettextColor(59,62,239);
$pdf->Ln(30);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("OBSERVATIONS DU RESPONSABLE PEDAGOGIQUE"));
$pdf->Ln(10);
$pdf->SettextColor(0,0,0);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(10);
////////////////// FIN FICHE OBSERVATIONS A1 ////////////////////////////////////
////////////////// DEBUT TRANSITION A2 //////////////////////////////////////////
$pdf->AddPage();
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$posX3 = $posX;
$posY3 = $posY + 260;
$posX4 = $posX2;
$posY4 = $posY2 + 260;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->SetFont('Arial','B',36);
$pdf->SetTextColor(68,72,230);
$pdf->Ln(110);
$pdf->Cell(35);
$pdf->MultiCell(130,5,utf8_decode("DEUXIÈME ANNÉE"));
$pdf->Ln(20);
$pdf->Cell(31);
$pdf->MultiCell(130,5,utf8_decode("D'APPRENTISSAGE"));
$pdf->Ln(40);
$pdf->Cell(77);
$pdf->SetFont('Arial','B',15);
$pdf->MultiCell(130,5,utf8_decode("2024 - 2025"));
////////////////// FIN TRANSITION A2 ////////////////////////////////////
////////////////// DEBUT EQUIPE PEDAGOGIQUE A2 //////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->SetTextColor(58,58,240);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("EQUIPE PEDAGOGIQUE - 2EME ANNEE"));
$pdf->SetTextColor(0,0,0);
$pdf->Ln(15);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$posX6 = $posX2 / 2 + 20;
$posY6 = $posY2;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("MATIERE"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode("Formateur"));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Culture économique juridique et managériale"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Algorithmique et CCF"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Anglais"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Administration avancée windows"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Architecture réseaux"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Supervision des réseaux"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Mathématiques et CCF"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Accompagnement E5"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Administration avancée Linux"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Architecture système"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Exploitation des services"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Gestion parc – assistance utilisateur"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Scritpting et sauvegardes"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Travailler en mode projets"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Culture Générale et expression"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Administration systèmes"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->Cell(5);
$pdf->Cell(100,10,utf8_decode("Cybersécurité"));
$pdf->Cell(10);
$pdf->Cell(180,10,utf8_decode(""));
$pdf->SetFont('Arial','',11);
$pdf->Ln(7);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$posX7 = $posX6;
$posY7 = $posY3;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX6, $posY6, $posX7, $posY7);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN EQUIPE PEDAGOGIQUE A2 ///////////////////////////////////
////////////////// DEBUT COMPTES RENDUS S-O A2 /////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Nom - Prénom : ..........................................................."));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Septembre / Octobre 2024"));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Compte-rendu d'activités en Entreprise"));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->MultiCell(180,5,utf8_decode('Activités professionnelles confiées en Entreprise'));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode('Missions, définitions et avancées des objectifs fixés, progrès en entreprise..., etc.'));
$pdf->Ln(50);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations de l'apprenti"));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->MultiCell(180,5,utf8_decode('Principales découvertes, difficultés de compréhension, liens entre les connaissances et les activités en entreprise, etc.'));
$pdf->Ln(40);
$pdf->Cell(160);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du tuteur/maître d'apprentissage"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du référent de groupe"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN COMPTES RENDUS S-O A2 ///////////////////////////////////
////////////////// DEBUT COMPTES RENDUS N-D A2 /////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Nom - Prénom : ..........................................................."));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Novembre / Décembre 2024"));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Compte-rendu d'activités en Entreprise"));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->MultiCell(180,5,utf8_decode('Activités professionnelles confiées en Entreprise'));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode('Missions, définitions et avancées des objectifs fixés, progrès en entreprise..., etc.'));
$pdf->Ln(50);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations de l'apprenti"));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->MultiCell(180,5,utf8_decode('Principales découvertes, difficultés de compréhension, liens entre les connaissances et les activités en entreprise, etc.'));
$pdf->Ln(40);
$pdf->Cell(160);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du tuteur/maître d'apprentissage"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du référent de groupe"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN COMPTES RENDUS N-D A2 ///////////////////////////////////
////////////////// DEBUT COMPTES RENDUS J-F A2 /////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Nom - Prénom : ..........................................................."));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Janvier / Février 2025"));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Compte-rendu d'activités en Entreprise"));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->MultiCell(180,5,utf8_decode('Activités professionnelles confiées en Entreprise'));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode('Missions, définitions et avancées des objectifs fixés, progrès en entreprise..., etc.'));
$pdf->Ln(50);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations de l'apprenti"));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->MultiCell(180,5,utf8_decode('Principales découvertes, difficultés de compréhension, liens entre les connaissances et les activités en entreprise, etc.'));
$pdf->Ln(40);
$pdf->Cell(160);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du tuteur/maître d'apprentissage"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du référent de groupe"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN COMPTES RENDUS J-F A2 ///////////////////////////////////
////////////////// DEBUT COMPTES RENDUS M-A A2 /////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Nom - Prénom : ..........................................................."));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Mars / Avril 2025"));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Compte-rendu d'activités en Entreprise"));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->MultiCell(180,5,utf8_decode('Activités professionnelles confiées en Entreprise'));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode('Missions, définitions et avancées des objectifs fixés, progrès en entreprise..., etc.'));
$pdf->Ln(50);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations de l'apprenti"));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->MultiCell(180,5,utf8_decode('Principales découvertes, difficultés de compréhension, liens entre les connaissances et les activités en entreprise, etc.'));
$pdf->Ln(40);
$pdf->Cell(160);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du tuteur/maître d'apprentissage"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du référent de groupe"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN COMPTES RENDUS M-A A2 ///////////////////////////////////
////////////////// DEBUT COMPTES RENDUS M-J A2 /////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Nom - Prénom : ..........................................................."));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Mai / Juin 2025"));
$pdf->Ln(10);
$pdf->Cell(5);
$pdf->Cell(180,10,utf8_decode("Compte-rendu d'activités en Entreprise"));
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->SetFillColor(255,255,255);
$pdf->Cell(5);
$posX = $pdf->getX();
$posY = $pdf->getY();
$posX2 = $posX + 180;
$posY2 = $posY;
$pdf->Line($posX, $posY, $posX2, $posY2);
$pdf->MultiCell(180,5,utf8_decode('Activités professionnelles confiées en Entreprise'));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode('Missions, définitions et avancées des objectifs fixés, progrès en entreprise..., etc.'));
$pdf->Ln(50);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations de l'apprenti"));
$pdf->Ln(5);
$pdf->SetFont('Arial','',12);
$pdf->Cell(5);
$pdf->MultiCell(180,5,utf8_decode('Principales découvertes, difficultés de compréhension, liens entre les connaissances et les activités en entreprise, etc.'));
$pdf->Ln(40);
$pdf->Cell(160);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du tuteur/maître d'apprentissage"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
$pdf->MultiCell(180,5,utf8_decode("Observations du référent de groupe"));
$pdf->Ln(30);
$pdf->Cell(160);
$pdf->SetFont('Arial','',12);
$pdf->Cell(180,5,utf8_decode("+ signature"));
$pdf->Ln(10);
$pdf->Cell(5);
$posX3 = $pdf->getX();
$posY3 = $pdf->getY();
$posX4 = $posX3 + 180;
$posY4 = $posY3;
$pdf->Line($posX3, $posY3, $posX4, $posY4);
$pdf->Line($posX, $posY, $posX3, $posY3);
$pdf->Line($posX2, $posY2, $posX4, $posY4);
////////////////// FIN COMPTES RENDUS M-J A2 ///////////////////////////////////
////////////////// DEBUT FICHE OBSERVATION A2 //////////////////////////////////
$pdf->AddPage();
$pdf->SetFont('Arial','B',13);
$pdf->SettextColor(59,62,239);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("OBSERVATIONS DE L'APPRENTI"));
$pdf->Ln(10);
$pdf->SettextColor(0,0,0);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->SetFont('Arial','B',13);
$pdf->SettextColor(59,62,239);
$pdf->Ln(30);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("OBSERVATIONS DU RESPONSABLE PEDAGOGIQUE"));
$pdf->Ln(10);
$pdf->SettextColor(0,0,0);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(15);
$pdf->Cell(5);
$pdf->Cell(180,5,utf8_decode("............................................................................................................................................"));
$pdf->Ln(10);
$pdf->Output("F", "documentsPdf/reste.pdf");
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(1);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(2);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(3);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(4);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(5);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(6);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(7);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(8);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(9);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(10);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(11);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(12);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(13);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(14);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(15);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(16);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(17);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(18);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(19);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(20);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(21);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(22);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->AddPage();
// set the source file
$pdfi->setSourceFile("documentsPdf/reste.pdf");
// import page 1
$tplId = $pdfi->importPage(23);
// use the imported page and place it at point 10,10 with a width of 100 mm
$pdfi->useTemplate($tplId, 0, 0, 210);
$pdfi->Output();
?>
