<?php
/* Copyright (C) 2002-2007  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/compta/facture/class/facture.class.php
 *  \ingroup    invoice
 *  \brief      File of class to manage invoices
 */
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';



class Cron_DynamicsPrices
{
        /**
     *  Constructor
     *
     *  @param  DoliDB      $db         Database handler
     */
    public function __construct(DoliDB $db)
    {
        global $conf, $langs ;
        $this->db = $db;
        //var_dump($db);
        $this->ismultientitymanaged = 1;
        $this->isextrafieldmanaged = 1;
    }

    public function updatePrices()
    {
        global $db, $conf, $user, $langs;

        $langs->load("dynamicsprices@dynamicsprices");

        $error = 0;

        try {
            $db->begin();

            require_once __DIR__.'/../lib/dynamicsprices.lib.php';
            if (getDolGlobalString('LMDB_COST_PRICE_ONLY')) {
                $results = update_customer_prices_from_cost_price($db, $user, $conf, $langs);
            } else {
                $results = update_customer_prices_from_suppliers($db, $user, $conf, $langs);
            }
            
            //var_dump('$nb_line10 = '.$results.'<br>');
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            dol_syslog("DynamicsPrices CRON ERROR: ".$e->getMessage(), LOG_ERR);
            $error++;
        }
        //var_dump('$error = '.$error.'<br>');
        //var_dump('$nb_line10 = '.$nb_line.'<br>');

        if ($error) {
            $this->error = $langs->trans('LMDB_ErrorUpdate').' '.$error;
            dol_syslog(__METHOD__." end - ".$this->error, LOG_INFO);
            return 1;
        }else{
            $this->output = $langs->trans('LMDB_NbLinesUpdated')." ".$results.".";
            dol_syslog(__METHOD__." end - ".$this->output, LOG_INFO);
            return 0;
        }
        
    }
}
