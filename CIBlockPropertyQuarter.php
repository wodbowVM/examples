<?php

use \Bitrix\Main\Type,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Iblock\PropertyTable,
    \Bitrix\Main\ORM\Fields\DateField;

/**
 * Класс кастомного свойства Квартал (трёхмесячный период)
 * За основу взят класс даты CIBlockPropertyDate housage.es/bitrix/modules/iblock/classes/general/prop_date.php
 *
 * Будем хранить как тип Дата, должен храниться 1 день указанного квартала, то есть к примеру, 01.01.2020 - это I квартал 2020 года
 *
 * Class CIBlockPropertyQuarter
 */

class CIBlockPropertyQuarter
{

    const MIN_YEAR_DEFAULT_VALUE = 2019;
    const MAX_YEAR_DEFAULT_VALUE = 5;

    /** @var array Статическая переменная для хранения настроек */
    static $arSettings = [];

    /**
     * Возвращает описание свойства
     *
     * @return array
     */
    public static function GetUserTypeDescription()
    {

        $result = [
            "PROPERTY_TYPE"        => PropertyTable::TYPE_STRING,
            "USER_TYPE"            => 'quarter',
            "DESCRIPTION"          => Loc::getMessage('IBLOCK_CUSTOM_PROPERTY_QUARTER_DESCRIPTION'),
            //optional handlers
            "GetPublicViewHTML"    => [__CLASS__, "getPublicViewHTML"],
            "GetPublicEditHTML"    => [__CLASS__, "getPublicEditHTML"],
            "GetAdminListViewHTML" => [__CLASS__, "getAdminListViewHTML"],
            "GetPropertyFieldHtml" => [__CLASS__, "getPropertyFieldHtml"],

            "ConvertToDB"          => [__CLASS__, "convertToDB"],
            "ConvertFromDB"        => [__CLASS__, "convertFromDB"],

            "CheckFields"          => [__CLASS__, "checkFields"],
            "PrepareSettings" 	   => [__CLASS__, "prepareSettings"],
            "GetSettingsHTML"      => [__CLASS__, "getSettingsHTML"],
            "GetORMFields"         => [__CLASS__, "getORMFields"],

            "GetAdminFilterHTML"   => [__CLASS__, "getAdminFilterHTML"],
            "AddFilterFields"	   => [__CLASS__, "addFilterFields"],
            "GetPublicFilterHTML"  => [__CLASS__, "getPublicFilterHTML"]
        ];

        return $result;
    }

    /**
     * Возвращает подготовленные дополнительные опции
     *
     * @param $arProperty
     *
     * @return array
     */
    public static function prepareSettings($arProperty)
    {
        if (!empty(self::$arSettings)) {
            return self::$arSettings;
        }

        /** Получим дополнительные опции $arSettings */
        if (is_array($arProperty["USER_TYPE_SETTINGS"])) {
            $arSettings = $arProperty["USER_TYPE_SETTINGS"];
        } else {
            $arSettings = unserialize($arProperty["USER_TYPE_SETTINGS"]);
        }

        $arSettings['MIN_YEAR'] = intval($arSettings['MIN_YEAR']);
        if (empty($arSettings['MIN_YEAR'])) {
            $arSettings['MIN_YEAR'] = self::MIN_YEAR_DEFAULT_VALUE;
        }

        $arSettings['MAX_YEAR'] = intval($arSettings['MAX_YEAR']);
        if (empty($arSettings['MAX_YEAR'])) {
            $arSettings['MAX_YEAR'] = self::MAX_YEAR_DEFAULT_VALUE;
        }

        self::$arSettings = $arSettings;
        return self::$arSettings;
    }

    /**
     * Возвращает HTML код с дополнительными опциями свойства для отображения в форме "Настройка свойства инфоблока"
     *
     * @param $arProperty
     * @param $strHTMLControlName
     * @param $arPropertyFields
     *
     * @return string
     */
    public static function getSettingsHTML($arProperty, $strHTMLControlName, &$arPropertyFields)
    {
        $arSettings = self::prepareSettings($arProperty);

        /** Скроем лишние настройки */
        $arPropertyFields = [
            "HIDE" => [
                "ROW_COUNT",
                "COL_COUNT",
                "MULTIPLE_CNT",
                "MULTIPLE",
                "SIZE",
                "WIDTH"
            ]
        ];

        /** Доп.опция: Минимальный год */
        $strSettings = '
			<tr>
				<td>' . Loc::getMessage('IBLOCK_CUSTOM_PROPERTY_QUARTER__MIN_YEAR__LABEL_TEXT') . ':</td>
				<td>
					<input 
                        type="number" 
                        name="' . $strHTMLControlName["NAME"] . '[MIN_YEAR]" 
                        value="' . (int)$arSettings['MIN_YEAR'] . '"
					>
				</td>
			</tr>
        ';

        /** Доп.опция: Максимальный год */
        $strSettings .= '
			<tr>
				<td>' . Loc::getMessage('IBLOCK_CUSTOM_PROPERTY_QUARTER__MAX_YEAR__LABEL_TEXT') . ':</td>
				<td>
					+<input 
                        type="number" 
                        name="' . $strHTMLControlName["NAME"] . '[MAX_YEAR]" 
                        value="' . (int)$arSettings['MAX_YEAR'] . '"
					>
				</td>
			</tr>
        ';

        return $strSettings;
    }

    /**
     * Преобразует представление данных для вывода
     *
     * @param $arProperty
     * @param $value
     * @param string $format
     *
     * @return mixed
     */
    public static function convertFromDB($arProperty, $value, $format = '')
    {
        if (empty($value["VALUE"])) {
            return '';
        }

        $arDate = explode('-', $value["VALUE"]);

        $value['VALUE'] = [
            'BASE' => $value["VALUE"],
            'QUARTER' => self::getQuarterNumber(intval($arDate[1])),
            'MONTH' => intval($arDate[1]),
            'YEAR' => intval($arDate[0]),
            'FORMATTED' => Loc::getMessage('IBLOCK_CUSTOM_PROPERTY_QUARTER_NUM_MONTH_NUMBER_' . intval($arDate[1])) . ' ' .
                Loc::getMessage('IBLOCK_CUSTOM_PROPERTY_QUARTER_WORD') . ' ' . intval($arDate[0])
        ];

        return $value;
    }

    /**
     * Преобразует значение свойства из представления в форме в дату для сохранения в БД
     *
     * @param $arProperty
     * @param $value
     *
     * @return mixed
     *
     * @throws Exception
     */
    public static function convertToDB($arProperty, $value)
    {
        if (
            is_array($value["VALUE"]) &&
            intval($value["VALUE"]['MONTH']) > 0 &&
            intval($value["VALUE"]['YEAR']) > 0
        ) {
            // Добавим ведущий 0 при необходимости
            if ($value["VALUE"]['MONTH'] < 10) {
                $value["VALUE"]['MONTH'] = '0' . $value["VALUE"]['MONTH'];
            }
            $value["VALUE"] = $value["VALUE"]['YEAR'] . '-' . $value["VALUE"]['MONTH'] . '-01';
        } else {
            $value["VALUE"] = '';
        }

        return $value;
    }

    /**
     * Возвращает HTML код свойства для публичного инпута
     *
     * @param $arProperty
     * @param $value
     * @param $strHTMLControlName
     *
     * @return string
     */
    public static function getPublicEditHTML($arProperty, $value, $strHTMLControlName)
    {
        return self::getPropertyFieldHtml($arProperty, $value, $strHTMLControlName);
    }

    /**
     * Возвращает HTML код свойства для отображения
     *
     * @param $arProperty
     * @param $value
     * @param $strHTMLControlName
     *
     * @return string
     */
    public static function getPropertyFieldHtml($arProperty, $value, $strHTMLControlName)
    {
        $arSettings = self::prepareSettings($arProperty);
        $fieldStringId = md5($strHTMLControlName);

        $isYearFound = false;
        if (empty($value['VALUE']['YEAR'])) {
            $isYearFound = true;
        }
        
        $s = '
        <select name="' . htmlspecialcharsbx($strHTMLControlName["VALUE"]) . '[MONTH]" id="'.$fieldStringId.'_month" onchange="this.value.length == 0 ? document.getElementById(\''.$fieldStringId.'_year\').value = \'\' : true;">
            <option value="">-</option>';
            for ($month = 1; $month < 12; $month +=3) {
                $s .= '
                <option 
                    value="' . $month . '" ' .
                    ($value['VALUE']['MONTH'] == $month ? ' selected="selected" ' : '') . '
                    >' . Loc::getMessage('IBLOCK_CUSTOM_PROPERTY_QUARTER_NUM_MONTH_NUMBER_' . $month) .
                '</option>
                ';
            }


        $s .= '
        </select> 
        &nbsp;
        ' . Loc::getMessage('IBLOCK_CUSTOM_PROPERTY_QUARTER_WORD') . '
        &nbsp;
        <select name="' . htmlspecialcharsbx($strHTMLControlName["VALUE"]) . '[YEAR]" id="'.$fieldStringId.'_year" onchange="this.value.length == 0 ? document.getElementById(\''.$fieldStringId.'_month\').value = \'\' : true;">
            <option value="">-</option>';
            for ($year = $arSettings['MIN_YEAR']; $year <= (date('Y') + $arSettings['MAX_YEAR']); $year++) {
                $s .= '
                    <option 
                    value="' . $year . '"';
                    if ($value['VALUE']['YEAR'] == $year)  {
                        $isYearFound = true;
                        $s .= ' selected="selected" ';
                    }
                    $s .='>' . $year . '</option>
                    ';
            }
            if (!$isYearFound) {
                $s .= '
                <option value="' . $value['VALUE']['YEAR'] . '" selected="selected">' . $value['VALUE']['YEAR'] . '</option>
                ';
            }
            $s .= '
        </select> 
        ';

        return $s;
    }

    /**
     * @param $arProperty
     * @param $value
     * @param $strHTMLControlName
     *
     * @return mixed|string|string[]
     */
    public static function getPublicViewHTML($arProperty, $value, $strHTMLControlName)
    {
        return self::getAdminListViewHTML($arProperty, $value, $strHTMLControlName);
    }

    /**
     * Возвращает HTML код со значением свойства для отображения в админке в списке
     *
     * @param $arProperty
     * @param $value
     * @param $strHTMLControlName
     *
     * @return mixed|string
     */
    public static function getAdminListViewHTML($arProperty, $value, $strHTMLControlName)
    {
        $result = '&nbsp;';

        $month = $value["VALUE"]['MONTH'];
        $year = $value["VALUE"]['YEAR'];
        if (intval($month) > 0 && intval($year) > 0) {
            $result = Loc::getMessage('IBLOCK_CUSTOM_PROPERTY_QUARTER_NUM_MONTH_NUMBER_' . $month) . '&nbsp;' .
                Loc::getMessage('IBLOCK_CUSTOM_PROPERTY_QUARTER_WORD') . '&nbsp;' . $year;
        }

        return $result;
    }

    /**
     * Пришлось оставить такую заглушку, иначе боюсь могут быть эксцессы
     *
     * @param $arUserField
     * @param $value
     *
     * @return array
     */
    public static function checkFields($arUserField, $value)
    {
        $aMsg = [];
        return $aMsg;
    }

    /**
     * @param \Bitrix\Main\ORM\Entity $valueEntity
     * @param \Bitrix\Iblock\Property $property
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     */
    public static function getORMFields($valueEntity, $property)
    {
        $valueEntity->addField(
            (new DateField('DATE'))
                ->configureFormat('Y-m-d')
                ->configureColumnName($valueEntity->getField('VALUE')->getColumnName())
        );
    }

    /**
     * По идее этот метод должен вызываться для формирования HTML кода для UI фильтра в админке,
     * но вместо него вызывается getPublicFilterHTML
     *
     * @param $arProperty
     * @param $strHTMLControlName
     *
     * @return false|string
     *
     * @throws \Bitrix\Main\LoaderException
     */
    public static function getAdminFilterHTML($arProperty, $strHTMLControlName) {
        return self::getPropertyFilterFieldHtml($arProperty, $strHTMLControlName);
    }

    /**
     * Вызывается для формирования HTML кода для UI фильтра в админке
     *
     * @param $arProperty
     * @param $strHTMLControlName
     *
     * @return false|string
     *
     * @throws \Bitrix\Main\LoaderException
     */
    public static function getPublicFilterHTML($arProperty, $strHTMLControlName) {
        return self::getPropertyFilterFieldHtml($arProperty, $strHTMLControlName);
    }

    /**
     * Возвращает HTML код для формы фильтрации
     *
     * @param $arProperty
     * @param $strHTMLControlName
     *
     * @return false|string
     *
     * @throws \Bitrix\Main\LoaderException
     */
    function getPropertyFilterFieldHtml($arProperty, $strHTMLControlName)
    {
        global $APPLICATION;

        /** Получим значения фильтра и из него возьмём заполненное значение нашего свойства */
        $filter = [];
        $filterOption = new Bitrix\Main\UI\Filter\Options($GLOBALS['sTableID']);
        $filterData = $filterOption->getFilter([]);
        foreach ($filterData as $k => $v) {
            $filter[$k] = $v;
        }
        $filterValue = $filter['PROPERTY_' . $arProperty['ID']];

        /**
         * Сформируем отображение
         */
        $result = '';

        ob_start();
        $APPLICATION->IncludeComponent(
            'housage:quarter.ui.filter',
            '',
            [
                'PROPERTY_ID' => $arProperty['ID'],
                'VALUE_STRING' => $filterValue,
                'VALUE' => self::getDateAsArray($filterValue),
                'CONTROL_NAME_VALUE' => $strHTMLControlName["VALUE"],
                'CONTROL_NAME_DESCRIPTION' => $strHTMLControlName["DESCRIPTION"],
                'PROPERTY_SETTINGS' =>  self::prepareSettings($arProperty),
            ],
            null,
            ['HIDE_ICONS' => 'Y']
        );
        $result .= ob_get_contents();
        ob_end_clean();

        return $result;
    }

    /**
     * Добавляет в фильтр поле (для GetList)
     *
     * @param $arProperty
     * @param $strHTMLControlName
     * @param $arFilter
     * @param $filtered
     *
     * @throws Exception
     */
    public static function addFilterFields($arProperty, $strHTMLControlName, &$arFilter, &$filtered)
    {
        $filtered = false;
        $arDate = self::getDateAsArray($arFilter[$strHTMLControlName["VALUE"]]);

        if (!empty($arDate['FROM']['DATE'])) {
            $arFilter[">=PROPERTY_" . $arProperty["ID"]] = $arDate['FROM']['DATE'];
            unset($arFilter[$strHTMLControlName["VALUE"]]);
            $filtered = true;
        }

        if (!empty($arDate['TO']['DATE'])) {
            $arFilter["<=PROPERTY_".$arProperty["ID"]] = $arDate['TO']['DATE'];
            unset($arFilter[$strHTMLControlName["VALUE"]]);
            $filtered = true;
        }
    }

    /**
     * Из значения строки фильтра формирует массив для передачи в компонент housage:quarter.ui.filter
     *
     * @param $value
     *
     * @return array
     */
    private static function getDateAsArray($value) {
        $arResult = [];

        if (stripos($value, '#') !== false) {
            $arDate = explode('#', $value);
            $arDateFrom = explode('-', $arDate['2']);
            $arDateTo = explode('-', $arDate['4']);
            $arResult = [
                'FROM' => [
                    'DATE' => $arDate['2'],
                    'MONTH' => intval($arDateFrom[1]),
                    'YEAR' => intval($arDateFrom[0])
                ],
                'TO' => [
                    'DATE' => $arDate['4'],
                    'MONTH' => intval($arDateTo[1]),
                    'YEAR' => intval($arDateTo[0])
                ]
            ];
        } else {
            $arDate = explode('-', $value);
            $arResult = [
                'DATE' => $value,
                'MONTH' => intval($arDate[1]),
                'YEAR' => intval($arDate[0])
            ];
        }

        return $arResult;
    }

    /**
     * Возвращает номер квартала по месяцу
     *
     * @param $month
     *
     * @return mixed
     */
    public static function getQuarterNumber($month) {
        $arQuarters = [
            1 => 1,
            4 => 2,
            7 => 3,
            10 => 4
        ];
        return $arQuarters[$month];
    }

    /**
     * Возвращает номер месяца по кварталу
     *
     * @param $quarter
     *
     * @return mixed
     */
    public static function getMonthByQuarter($quarter) {
        $arMonths = [
            1 => 1,
            2 => 4,
            3 => 7,
            4 => 10
        ];
        return $arMonths[$quarter];
    }

    /**
     * Добавляет ведущий ноль к месяцу
     *
     * @param $month
     *
     * @return string
     */
    public static function addZeroToMonth($month) {
        if ($month < 10) {
            return '0' . $month;
        }
        return $month;
    }
}