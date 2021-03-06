<?php
/* Copyright (C) 2011 		Juanjo Menent		<jmenent@2byte.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	    \file       htdocs/compta/localtax/index.php
 *      \ingroup    tax
 *		\brief      Index page of IRPF reports
 */
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/tax.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/tva/class/tva.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

$langs->load("other");

$year=$_GET["year"];
if ($year == 0 )
{
    $year_current = strftime("%Y",time());
    $year_start = $year_current;
} else {
    $year_current = $year;
    $year_start = $year;
}

// Security check
$socid = isset($_GET["socid"])?$_GET["socid"]:'';
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'tax', '', '', 'charges');

// Define modetax (0 or 1)
// 0=normal, 1=option vat for services is on debit
$modetax = $conf->global->TAX_MODE;
if (isset($_GET["modetax"])) $modetax=$_GET["modetax"];

function pt ($db, $sql, $date)
{
    global $conf, $bc,$langs;

    $result = $db->query($sql);
    if ($result)
    {
        $num = $db->num_rows($result);
        $i = 0;
        $total = 0;
        print '<table class="noborder" width="100%">';
        print '<tr class="liste_titre">';
        print '<td nowrap="nowrap" width="60%">'.$date.'</td>';
        print '<td align="right">'.$langs->trans("Amount").'</td>';
        print '<td>&nbsp;</td>'."\n";
        print "</tr>\n";
        $var=True;
        while ($i < $num)
        {
            $obj = $db->fetch_object($result);
            $var=!$var;
            print '<tr '.$bc[$var].'>';
            print '<td nowrap="nowrap">'.$obj->dm."</td>\n";
            $total = $total + $obj->mm;

            print '<td nowrap="nowrap" align="right">'.price($obj->mm)."</td><td >&nbsp;</td>\n";
            print "</tr>\n";

            $i++;
        }
        print '<tr class="liste_total"><td align="right">'.$langs->trans("Total")." :</td><td nowrap=\"nowrap\" align=\"right\"><b>".price($total)."</b></td><td>&nbsp;</td></tr>";

        print "</table>";
        $db->free($result);
    }
    else {
        dolibar_print_error($db);
    }
}


/*
 * View
 */

llxHeader();

$tva = new Tva($db);


$textprevyear="<a href=\"index.php?year=" . ($year_current-1) . "\">".img_previous()."</a>";
$textnextyear=" <a href=\"index.php?year=" . ($year_current+1) . "\">".img_next()."</a>";

print_fiche_titre($langs->transcountry("LT2",$mysoc->country_code),"$textprevyear ".$langs->trans("Year")." $year_start $textnextyear");

print $langs->trans("VATReportBuildWithOptionDefinedInModule").'<br>';
print '('.$langs->trans("TaxModuleSetupToModifyRules",DOL_URL_ROOT.'/admin/taxes.php').')<br>';
print '<br>';

print '<table width="100%" class="nobordernopadding">';
print '<tr><td>';
print_titre($langs->transcountry("LT2Summary",$mysoc->country_code));

print '</td><td width="5">&nbsp;</td><td>';
print_titre($langs->transcountry("LT2Paid",$mysoc->country_code));
print '</td></tr>';

print '<tr><td width="50%" valign="top">';

print "<table class=\"noborder\" width=\"100%\">";
print "<tr class=\"liste_titre\">";
print "<td width=\"30%\">".$langs->trans("Year")." $y</td>";
print "<td align=\"right\">".$langs->transcountry("LT2Customer",$mysoc->country_code)."</td>";
print "<td align=\"right\">".$langs->transcountry("LT2Supplier",$mysoc->country_code)."</td>";
print "<td align=\"right\">".$langs->trans("TotalToPay")."</td>";
print "<td>&nbsp;</td>\n";
print "</tr>\n";

$y = $year_current ;

$var=True;
$total=0; $subtotalcoll=0; $subtotalpaye=0; $subtotal=0;
$i=0;
for ($m = 1 ; $m < 13 ; $m++ )
{
    $coll_listsell = vat_by_date($db, $y, 0, 0, 0, $modetax, 'sell', $m);
    $coll_listbuy = vat_by_date($db, $y, 0, 0, 0, $modetax, 'buy', $m);

    if (! is_array($coll_listbuy) && $coll_listbuy == -1)
    {
        $langs->load("errors");
        print '<tr><td colspan="5">'.$langs->trans("ErrorNoAccountancyModuleLoaded").'</td></tr>';
        break;
    }
    if (! is_array($coll_listbuy) && $coll_listbuy == -2)
    {
        print '<tr><td colspan="5">'.$langs->trans("FeatureNotYetAvailable").'</td></tr>';
        break;
    }

    $var=!$var;
    print "<tr $bc[$var]>";
    print '<td nowrap>'.dol_print_date(dol_mktime(0,0,0,$m,1,$y),"%b %Y").'</td>';

    $x_coll = 0;
    foreach($coll_listsell as $vatrate=>$val)
    {
        $x_coll+=$val['localtax2'];
    }
    $subtotalcoll = $subtotalcoll + $x_coll;
    print "<td nowrap align=\"right\">".price($x_coll)."</td>";

    $x_paye = 0;
    foreach($coll_listbuy as $vatrate=>$val)
    {
        $x_paye+=$val['localtax2'];
    }
    $subtotalpaye = $subtotalpaye + $x_paye;
    print "<td nowrap align=\"right\">".price($x_paye)."</td>";

    $diff = $x_coll - $x_paye;
    $total = $total + $diff;
    $subtotal = $subtotal + $diff;

    print "<td nowrap align=\"right\">".price($diff)."</td>\n";
    print "<td>&nbsp;</td>\n";
    print "</tr>\n";

    $i++;
    if ($i > 2) {
        print '<tr class="liste_total">';
        print '<td align="right">'.$langs->trans("SubTotal").':</td>';
        print '<td nowrap="nowrap" align="right">'.price($subtotalcoll).'</td>';
        print '<td nowrap="nowrap" align="right">'.price($subtotalpaye).'</td>';
        print '<td nowrap="nowrap" align="right">'.price($subtotalpaye).'</td>';
        print '<td>&nbsp;</td></tr>';
        $i = 0;
        $subtotalcoll=0; $subtotalpaye=0; $subtotal=0;
    }
}
print '<tr class="liste_total"><td align="right" colspan="3">'.$langs->trans("TotalToPay").':</td><td nowrap align="right">'.price($total).'</td>';
print "<td>&nbsp;</td>\n";
print '</tr>';

print '</table>';

print '</td><td>&nbsp;</td><td valign="top" width="50%">';

/*
 * Payed
 */

$sql = "SELECT SUM(amount) as mm, date_format(f.datev,'%Y-%m') as dm";
$sql.= " FROM ".MAIN_DB_PREFIX."localtax as f";
$sql.= " WHERE f.entity = ".$conf->entity;
$sql.= " AND f.datev >= '".$db->idate(dol_get_first_day($y,1,false))."'";
$sql.= " AND f.datev <= '".$db->idate(dol_get_last_day($y,12,false))."'";
$sql.= " GROUP BY dm ASC";

pt($db, $sql,$langs->trans("Year")." $y");

print "</td></tr></table>";

print '</td></tr>';
print '</table>';

$db->close();

llxFooter();
?>
