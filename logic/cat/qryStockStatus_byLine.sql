SELECT 
        `st`.`ID` AS `ID`,
        `st`.`ID_Item` AS `ID_Item`,
        `st`.`ID_Bin` AS `ID_Bin`,
        IF(`sb`.`isForSale`, `st`.`Qty`, 0) AS `QtyForSale`,
        IF(`sb`.`isForShip`, `st`.`Qty`, 0) AS `QtyForShip`,
        `st`.`Qty` AS `QtyExisting`,
        `st`.`CatNum` AS `CatNum`,
        `st`.`WhenAdded` AS `WhenAdded`,
        `st`.`WhenChanged` AS `WhenChanged`,
        `st`.`WhenCounted` AS `WhenCounted`,
        `st`.`Notes` AS `Notes`,
        `sb`.`Code` AS `BinCode`,
        `sb`.`ID_Place` AS `ID_Place`,
        `sp`.`Name` AS `WhName`
    FROM
        ((`stk_lines` `st`
        LEFT JOIN `stk_bins` `sb` ON ((`sb`.`ID` = `st`.`ID_Bin`)))
        LEFT JOIN `stk_places` `sp` ON ((`sb`.`ID_Place` = `sp`.`ID`)))
    WHERE
        (ISNULL(`sb`.`WhenVoided`)
            AND (`st`.`Qty` <> 0)
            AND `sp`.`isActivated`)