<?php

use \Bitrix\Main\Localization\Loc,
    \Bitrix\Main\Loader,
    \Bitrix\Highloadblock as HL,
    \Bitrix\Iblock,
    \Bitrix\Main\Context,
    \Bitrix\Iblock\PropertyTable;

class CIBlockPropertyLocation {

    const MIN_HEIGHT_INPUT_DEFAULT_VALUE = 24; // Значение по умолчанию минимальной высоты textarea
    const MAX_HEIGHT_INPUT_DEFAULT_VALUE = 1000; // Значение по умолчанию максимальной высоты textarea
    const REPLACED_CHARS_DEFAULT_VALUE = ',;';
    const FORM_MODE_ELEMENT_LIST = 'iblock_element_admin'; // Код, определяющий что страница со списком элементов

    /**
     * Возвращает описание свойства
     *
     * @return array
     */
    function GetUserTypeDescription() {
        // Сформируем описание и укажем к каким методам обращаться
        return [
            "PROPERTY_TYPE" 		=> PropertyTable::TYPE_ELEMENT,
            "USER_TYPE" 			=> 'Location',
            "DESCRIPTION" 			=> Loc::getMessage('IBLOCK_CUSTOM_PROP_LOCATION__NAME'),
            "GetPropertyFieldHtml" 	=> [__CLASS__, "getPropertyFieldHtml"],
            "GetAdminListViewHTML" 	=> [__CLASS__, "getAdminListViewHTML"],
            "GetPublicViewHTML" 	=> [__CLASS__, "getPublicViewHTML"],
            "GetAdminFilterHTML" 	=> [__CLASS__, "getPropertyFilterFieldHtml"],
            "AddFilterFields"		=> [__CLASS__, "addFilterFields"],
            //optional handlers
            "CheckFields" 			=> [__CLASS__, "checkFields"],
            "PrepareSettings" 		=> [__CLASS__, "prepareSettings"],
            "GetSettingsHTML" 		=> [__CLASS__, "getSettingsHTML"],
        ];
    }

    /**
     * Возвращает подготовленные дополнительные опции
     *
     * @param $arProperty
     *
     * @return mixed
     */
    function prepareSettings($arProperty) {
        /** Получим дополнительные опции $arSettings */
        $arSettings = $arProperty["USER_TYPE_SETTINGS"];

        if (empty($arSettings) || !is_array($arSettings)) {
            $arSettings = [];
        }

        /** Проверим и откорректируем дополнительные опции */
        // Опция: Проверять активность локаций
        if ($arSettings["LOCATION_ACTIVE"] !== "Y") {
            $arSettings["LOCATION_ACTIVE"] = "N";
        }

        // Опция: Запрет на редактирование в админке
        if ($arSettings["DISABLED_EDIT"] !== "Y") {
            $arSettings["DISABLED_EDIT"] = "N";
        }

        // Опция: Показывать следующие типы локаций
        if (!is_array($arSettings['LOCATION_TYPES'])) {
            $arSettings['LOCATION_TYPES'] = [];
        }

        // Опция: Символ, который заменит при показе запрещенные символы
        if (is_array($arSettings['OTHER_REPLACED_SYMBOLS'])) {
            $arSettings['OTHER_REPLACED_SYMBOLS'] = implode('', $arSettings['OTHER_REPLACED_SYMBOLS']);
        } elseif (empty($arSettings['OTHER_REPLACED_SYMBOLS'])) {
            $arSettings['OTHER_REPLACED_SYMBOLS'] = '';
        }
        if (empty($arSettings["REPLACE_TO_SYMBOL"])) {
            $arSettings["REPLACE_TO_SYMBOL"] = '';
        }

        // Опция: Заменяемые при показе символы
        if (is_array($arSettings['REPLACED_CHARS'])) {
            $arSettings['REPLACED_CHARS'] = implode('', $arSettings['REPLACED_CHARS']);
        } elseif(empty($arSettings['REPLACED_CHARS'])) {
            $arSettings['REPLACED_CHARS'] = self::REPLACED_CHARS_DEFAULT_VALUE;
        }
        $arSettings['REPLACED_CHARS'] = str_replace(' ','', $arSettings['REPLACED_CHARS']);

        // Опция: Максимальная ширина поля ввода в пикселах
        $arSettings["MAX_WIDTH"] = intval($arSettings["MAX_WIDTH"]);
        if ($arSettings["MAX_WIDTH"] < 0) {
            $arSettings["MAX_WIDTH"] = 0;
        }

        // Опция: Минимальная высота поля ввода в пикселах, если свойство множественно
        $arSettings["MIN_HEIGHT"] = intval($arSettings["MIN_HEIGHT"]);
        if ($arSettings["MIN_HEIGHT"] < 0) {
            $arSettings["MIN_HEIGHT"] = self::MIN_HEIGHT_INPUT_DEFAULT_VALUE;
        }

        // Опция: Максимальная высота поля ввода в пикселах, если свойство множественное
        $arSettings["MAX_HEIGHT"] = intval($arSettings["MAX_HEIGHT"]);
        if ($arSettings["MAX_HEIGHT"] < 0) {
            $arSettings["MAX_HEIGHT"] = self::MAX_HEIGHT_INPUT_DEFAULT_VALUE;
        }

        // Опция: Свойство с зависимой локацией
        $arSettings["DEPENDENT_LOCATION"] = intval($arSettings["DEPENDENT_LOCATION"]);

        return $arSettings;
    }

    /**
     * Возвращает HTML код с дополнительными опциями свойства для отображения в форме "Настройка свойства инфоблока"
     *
     * @param $arProperty
     * @param $strHTMLControlName
     * @param $arPropertyFields
     *
     * @return string
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    function getSettingsHTML($arProperty, $strHTMLControlName, &$arPropertyFields) {
        /** Соберём необходимые данные */
        // Получим список типов
        $arLocationTypes = self::getLocationTypes();
        // Получим дополнительные опции
        $arSettings = self::prepareSettings($arProperty);

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

        /** Доп.опция: Проверять активность локаций */
        $strSettings = '
			<tr valign="top">
				<td>' . Loc::getMessage('IBLOCK_CUSTOM_PROP_LOCATION__OPTION_LOCATION_ACTIVE') . '</td>
				<td>
                    <input
                        type="checkbox"
                        name="' . $strHTMLControlName["NAME"] . '[LOCATION_ACTIVE]"
                        value="Y" ' . ($arSettings["LOCATION_ACTIVE"] == "Y" ? 'checked' : '') . '>
				</td>
			</tr>
		';

        /** Доп.опция: Запретить редактирование свойства в админке */
        $strSettings .= '
			<tr valign="top">
				<td>' . Loc::getMessage('IBLOCK_CUSTOM_PROP_LOCATION__OPTION_DISABLED_EDIT') . '</td>
				<td>
                    <input
                        type="checkbox"
                        name="' . $strHTMLControlName["NAME"] . '[DISABLED_EDIT]"
                        value="Y" ' . ($arSettings["DISABLED_EDIT"] == "Y" ? 'checked' : '') . '>
				</td>
			</tr>
		';

        /** Доп.опция: Показывать следующие типы локаций */
        // На основе списка типов сформируем строку таблицы с настройками
        if (!empty($arLocationTypes)) {
            $strSettings .= '
            <tr valign="top">
                <td>' . Loc::getMessage('IBLOCK_CUSTOM_PROP_LOCATION__OPTION_LOCATION_TYPES') . '</td>
                <td>
                    <select name="' . $strHTMLControlName["NAME"] . '[LOCATION_TYPES][]" multiple>';
            foreach ($arLocationTypes as $arLocationType) {
                $strSettings .= '
                <option
                    value="' . $arLocationType['CODE'] . '"
                    ' . (in_array($arLocationType['CODE'], $arSettings['LOCATION_TYPES']) ? 'selected' : '') . '
                    >' . $arLocationType['NAME'] .
                '</option>';
            }
            $strSettings .= '
                    </select>
                </td>
            </tr>
            ';
        }

        /** Доп.опция: Символ, который заменит при показе запрещенные символы */
        $otherReplacedSymbolsSelectHtml = SelectBoxFromArray(
            $strHTMLControlName["NAME"] . '[OTHER_REPLACED_SYMBOLS]',
            static::getReplaceSymbolsList(true),
            htmlspecialcharsbx($arSettings['OTHER_REPLACED_SYMBOLS'])
        );
        $strSettings .= '
			<tr>
				<td>' . Loc::getMessage('IBLOCK_CUSTOM_PROP_LOCATION__CHARS') . ':</td>
				<td>
					' . $otherReplacedSymbolsSelectHtml . '&nbsp;<input
                                    type="text"
                                    name="' . $strHTMLControlName["NAME"] . '[REPLACE_TO_SYMBOL]"
                                    size="10" maxlength="10"
                                    value="' . $arSettings['REPLACE_TO_SYMBOL'] . '"
                                    >
				</td>
			</tr>
        ';

        /** Доп.опция: Заменяемые при показе символы */
        $strSettings .= '
			<tr>
				<td>' . Loc::getMessage('IBLOCK_CUSTOM_PROP_LOCATION__REPLACED_CHARS') . ':</td>
				<td>
					<input
                        type="text"
                        name="' . $strHTMLControlName["NAME"] . '[REPLACED_CHARS]"
                        value="' . htmlspecialcharsbx($arSettings['REPLACED_CHARS']) . '"
                        >
				</td>
			</tr>
        ';

        /** Доп.опция: Максимальная ширина поля ввода в пикселах (0 - не ограничивать) */
        $strSettings .= '
			<tr>
				<td>' . Loc::getMessage("IBLOCK_CUSTOM_PROP_LOCATION__MAX_WIDTH") . ':</td>
				<td>
					<input
                        type="text"
                        name="' . $strHTMLControlName["NAME"] . '[MAX_WIDTH]"
                        value="' . (int)$arSettings['MAX_WIDTH'] . '"
					>&nbsp;px
				</td>
			</tr>
        ';

        /** Доп.опция: Минимальная высота поля ввода в пикселах, если свойство множественное */
        $strSettings .= '
			<tr>
				<td>' . Loc::getMessage('IBLOCK_CUSTOM_PROP_LOCATION__MIN_HEIGHT') . ':</td>
				<td>
					<input
                        type="text"
                        name="' . $strHTMLControlName["NAME"] . '[MIN_HEIGHT]"
                        value="' . (int)$arSettings['MIN_HEIGHT'] . '"
					>&nbsp;px
				</td>
			</tr>
        ';

        /** Доп.опция: Максимальная высота поля ввода в пикселах, если свойство множественное */
        $strSettings .= '
			<tr>
				<td>' . Loc::getMessage('IBLOCK_CUSTOM_PROP_LOCATION__MAX_HEIGHT') . ':</td>
				<td>
					<input
                        type="text"
                        name="' . $strHTMLControlName["NAME"] . '[MAX_HEIGHT]"
                        value="' . (int)$arSettings['MAX_HEIGHT'] . '"
					>&nbsp;px
				</td>
			</tr>
        ';

        /** Доп.опция: Свойство с зависимой локацией */
        $arDependentLocation = self::getDependentLocations($arProperty);
        $strSettings .= '
			<tr>
				<td>' . Loc::getMessage('IBLOCK_CUSTOM_PROP_LOCATION__DEPENDENT_LOCATION') . ':</td>
				<td>
					<select name="' . $strHTMLControlName["NAME"] . '[DEPENDENT_LOCATION]" >
					    <option value="0">EMPTY</option>';
            foreach ($arDependentLocation as $arLocation) {
                $strSettings .= '
                <option
                    value="' . $arLocation['ID'] . '"
                    ' . ($arLocation['ID'] == $arSettings['DEPENDENT_LOCATION'] ? 'selected' : '') . '
                    >' . $arLocation['NAME'] .
                '</option>';
            }
            $strSettings .= '
                    </select>
				</td>
			</tr>
        ';

        return $strSettings;
    }

    /**
     * Формирует массив возможных значений для свойства с зависимой локацией
     *
     * @param $arProperty
     *
     * @return array
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private function getDependentLocations($arProperty) {
        $arResult = [];
        /**
         * Выберем свойства
         */
        $rsProperties = PropertyTable::getList([
            'filter' => [
                'ACTIVE' => 'Y',
                'IBLOCK_ID' => $arProperty['IBLOCK_ID'],
                'USER_TYPE' => 'Location',
                '!ID' => $arProperty['ID']
            ]
        ]);

        while ($arProp = $rsProperties->fetch()) {
            $arResult[] = [
                'ID' => $arProp['ID'], 'NAME' => $arProp['ID'] . ' ' . $arProp['NAME'] . '[' . $arProp['CODE'] . ']'
            ];
        }

        return $arResult;
    }

    /**
     * Возвращает HTML код свойства для редактирования
     *
     * @param $arProperty
     * @param $value
     * @param $strHTMLControlName
     *
     * @return false|string
     *
     * @throws \Bitrix\Main\LoaderException
     */
    function getPropertyFieldHtml($arProperty, $value, $strHTMLControlName) {
        $arSettings = self::prepareSettings($arProperty);
        $arSymbols = static::getSymbols($arSettings);

        global $APPLICATION;

        // Для детального просмотра (оставим как значение по умолчанию)
        $inputName       = 'PROP[' . $arProperty['ID'] . '][n0]';
        $inputNameString = 'inp_PROP[' . $arProperty['ID'] . '][n0]';

        // Для списка элементов
        if ($strHTMLControlName['MODE'] == self::FORM_MODE_ELEMENT_LIST) {
            $inputName = 'FIELDS[' . $arProperty['PROPERTY_TYPE'] . $arProperty['PROPERTY_VALUE_ID'] .
                '][PROPERTY_' . $arProperty['ID'] . '][' . $arProperty['PROPERTY_VALUE_ID'] . ']';
            $inputNameString = 'inp_' . $inputName;
        }

        ob_start();
        $APPLICATION->IncludeComponent(
            "housage:location.lookup.input",
            "location_edit",
            [
                'PROPERTY_ID' => $arProperty['ID'],
                "CONTROL_ID" => 'PROP_' . $arProperty['ID'] . '_rndm_' . mt_rand(0, 999999),
                "INPUT_NAME" => $inputName,
                "INPUT_NAME_STRING" => $inputNameString,
                "INPUT_VALUE" => static::getPropertyValueString($value),
                'LOCATION_ID' => intval($value['VALUE']),
                "START_TEXT" => Loc::getMessage('IBLOCK_CUSTOM_PROP_LOCATION__START_TEXT'),
                "MULTIPLE" => ($arProperty['MULTIPLE'] == 'Y' ? : 'N' ),
                "MAX_WIDTH" => $arProperty['USER_TYPE_SETTINGS']['MAX_WIDTH'],
                "MIN_HEIGHT" => $arProperty['USER_TYPE_SETTINGS']['MIN_HEIGHT'],
                "MAX_HEIGHT" => $arProperty['USER_TYPE_SETTINGS']['MAX_HEIGHT'],
                "IBLOCK_ID" => IBLOCK_COUNTRIES,
                "ACTIVE_FILTER" => $arProperty['USER_TYPE_SETTINGS']['LOCATION_ACTIVE'],
                "LOCATION_TYPE_FILTER" => serialize($arProperty['USER_TYPE_SETTINGS']['LOCATION_TYPES']),
                'WITHOUT_IBLOCK' => 'N',
                'BAN_SYM' => $arSymbols['BAN_SYM_STRING'],
                'REP_SYM' => $arSymbols['REP_SYM_STRING'],
                'FILTER' => 'Y',
                'FILTER_FIELDS' => [
                    'PROPERTY_NAME_ES',
                    'PROPERTY_NAME_EN'
                ],
                'DEPENDENT_LOCATION' => $arSettings['DEPENDENT_LOCATION'],
                'DISABLED_EDIT' => $arProperty['USER_TYPE_SETTINGS']['DISABLED_EDIT']
            ],
            null,
            ["HIDE_ICONS" => "Y"]
        );
        $result = ob_get_contents();
        ob_end_clean();

        return $result;
    }

    /**
     * Возвращает HTML код со значением свойства для отображения в публичной части
     *
     * @param $arProperty
     * @param $value
     * @param $strHTMLControlName
     *
     * @return mixed|string
     *
     * @throws \Bitrix\Main\LoaderException
     */
    function getPublicViewHTML($arProperty, $value, $strHTMLControlName) {
        return self::getAdminListViewHTML($arProperty, $value, $strHTMLControlName);
    }

    /**
     * Возвращает HTML код со значением свойства для отображения в админке
     *
     * @param $arProperty
     * @param $value
     * @param $strHTMLControlName
     *
     * @return mixed|string
     *
     * @throws \Bitrix\Main\LoaderException
     */
    function getAdminListViewHTML($arProperty, $value, $strHTMLControlName) {
        $arSettings = self::prepareSettings($arProperty);
        $arSymbols = static::getSymbols($arSettings);

        if ($arProperty['MULTIPLE'] == 'Y') {
            $mxResultValue = static::getValueForAutoCompleteMulti($value, $arSymbols['BAN_SYM'], $arSymbols['REP_SYM']);
            $strResultValue = (is_array($mxResultValue) ? htmlspecialcharsback(implode('<br>', $mxResultValue)) : '');
        } else {
            $strResultValue = htmlspecialcharsback(static::getValueForAutoComplete(
                $value,
                $arSymbols['BAN_SYM'],
                $arSymbols['REP_SYM']
            ));
        }

        return $strResultValue;
    }

    /**
     * Добавляет в фильтр поле
     *
     * @param $arProperty
     * @param $strHTMLControlName
     * @param $arFilter
     * @param $filtered
     */
    function addFilterFields($arProperty, $strHTMLControlName, &$arFilter, &$filtered) {
        /** @var Bitrix\Main\HttpRequest $request */
        $request = Context::getCurrent()->getRequest();
        $filtered = true;
        $value = htmlspecialchars($request->get($strHTMLControlName['VALUE'])) ?: $arFilter[$strHTMLControlName['VALUE']];
        $arSettings = self::prepareSettings($arProperty);
        $isCityTypeAllowed = $arSettings['LOCATION_TYPES'] && in_array('city', $arSettings['LOCATION_TYPES']);

        /**
         *  Для фильтра по св-ву, где разрешен выбор города
         *  если текущая локация == регион, то отбираем его города и фильтруем объекты по городам
         */
        if (
            !$request->offsetExists('del_filter') &&
            !empty($value)
        ) {
            if (
                in_array($arProperty['IBLOCK_ID'], [IBLOCK_BUILDINGS, IBLOCK_CATALOG]) &&
                $isCityTypeAllowed
            ) {
                $arCity = CIBlockElement::GetList(
                    [],
                    [
                        'IBLOCK_ID' => IBLOCK_COUNTRIES,
                        'ID' => $value
                    ],
                    false,
                    ['nTopCount' => 1],
                    [
                        'ID',
                        'PROPERTY_LOCATION_TYPE'
                    ]
                )->Fetch();
                if ($arCity) {
                    if ($arCity['PROPERTY_LOCATION_TYPE_VALUE'] == 'region') {
                        $arCitiesIdList = [];
                        $res = CIBlockElement::GetList(
                            [],
                            [
                                'IBLOCK_ID' => IBLOCK_COUNTRIES,
                                'PROPERTY_LOCATION_PARENT' => $arCity['ID']
                            ],
                            false,
                            false,
                            ['ID']
                        );
                        while ($ar = $res->Fetch()) {
                            $arCitiesIdList[] = $ar['ID'];
                        }

                        if (!empty($arCitiesIdList)) {
                            $arFilter['PROPERTY_' . $arProperty['ID']] = $arCitiesIdList;
                        } else {
                            $arFilter['ID'] = false;
                        }

                        unset($arCitiesIdList);
                    } else {
                        $arFilter['PROPERTY_' . $arProperty['ID']] = $value;
                    }
                }
            }
            // В остальных случаях стандартно фильтруем по выбранному значению
            else {
                $arFilter['PROPERTY_' . $arProperty['ID']] = $value;
            }
        }
    }

    /**
     * Проверяет поле
     *
     * @param $arProperty
     * @param bool $value
     *
     * @return array|bool
     */
    function checkFields($arProperty, $value) {
        $result = [];
        $value = intval(is_array($value) ? $value['VALUE'] : $value);
        $arSettings = self::prepareSettings($arProperty);

        if ($value > 0) {
            $countResult = CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID' => IBLOCK_COUNTRIES,
                    'ACTIVE'	=> $arSettings['LOCATION_ACTIVE'] == 'Y' ? 'Y' : ['Y', 'N'],
                    'ID'		=> $value
                ],
                []
            );
            if ($countResult <= 0) {
                $result[] = 'Некорректное значение '.$arProperty['NAME'];
            }
        }

        return $result;
    }

    /**
     * Возвращает массив символов для замены
     *
     * @param bool $boolFull
     *
     * @return array
     */
    protected static function getReplaceSymbolsList($boolFull = false) {
        $boolFull = ($boolFull === true);

        if ($boolFull) {
            $result = [
                'REFERENCE' => [
                    Loc::getMessage('CUSTOM_PROP_USER_TYPE_IBLOCK_AUTOCOMPLETE_IBEL_WHITE_SPACE'),
                    '#',
                    '*',
                    '_',
                    'other'
                ],
                'REFERENCE_ID' => [
                    ' ',
                    '#',
                    '*',
                    '_',
                    'other'
                ]
            ];

            return $result;
        }

        return [' ', '#', '*', '_'];
    }

    /**
     * Формирует массив с правилами на замену в возвращаемых данных из компонента housage:location.lookup.input
     *
     * @param $arSettings
     *
     * @return array
     */
    protected static function getSymbols($arSettings) {
        // Заменяемые при показе символы
        $strBanSym = $arSettings['REPLACED_CHARS'];
        // На что заменять
        $strRepSym = ('other' == $arSettings['OTHER_REPLACED_SYMBOLS'] ? $arSettings['REPLACE_TO_SYMBOL'] : $arSettings['OTHER_REPLACED_SYMBOLS']);

        // Они же в виде массивов
        $arBanSym = str_split($strBanSym, 1);
        $arRepSym = array_fill(0, sizeof($arBanSym), $strRepSym);

        // Соберём итоговый массив
        $arResult = [
            'BAN_SYM' => $arBanSym,
            'REP_SYM' => $arRepSym,
            'BAN_SYM_STRING' => $strBanSym,
            'REP_SYM_STRING' => $strRepSym,
        ];

        return $arResult;
    }

    /**
     * Возвращает значение свойства (id локации) для передачи в компонент housage:location.lookup.input для формирования
     * отображения текущего значения в правильном виде
     *
     * @param $value
     *
     * @return bool|mixed|string
     *
     * @throws \Bitrix\Main\LoaderException
     */
    protected static function getPropertyValueString($value) {
        if (empty($value['VALUE'])) {
            return '';
        }

        $result = static::getElementData($value['VALUE']);

        return $result;
    }

    /**
     * Возвращает данные о локации для передачи в компонент housage:location.lookup.input
     *
     * @param $intElementID
     *
     * @return bool|mixed
     *
     * @throws \Bitrix\Main\LoaderException
     */
    protected static function getElementData($intElementID) {

        static $cache = [];

        $intElementID = (int)$intElementID;
        if ($intElementID <= 0) {
            return false;
        }

        if (!isset($cache[$intElementID])) {
            Loader::includeModule('iblock');

            $rsElements = \CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID' => IBLOCK_COUNTRIES,
                    'ID' => $intElementID,
                    'SHOW_HISTORY' => 'Y'
                ],
                false,
                false,
                [
                    'IBLOCK_ID',
                    'ACTIVE',
                    'ID',
                    'NAME',
                    'CODE',
                    'PROPERTY_LOCATION_TYPE',
                    'PROPERTY_NAME_ES'
                ]
            );
            if ($arElement = $rsElements->GetNext()) {
                $arResult = [
                    'ID'        => $arElement['ID'],
                    'NAME'      => ($arElement['PROPERTY_NAME_ES_VALUE'] ?: $arElement['NAME']),
                    '~NAME'     => ($arElement['~PROPERTY_NAME_ES_VALUE'] ?: $arElement['~NAME']),
                    'IBLOCK_ID' => $arElement['IBLOCK_ID'],
                    'CODE'      => ($arElement['CODE'] ?: 'no-code'),
                    'TYPE'      => $arElement['PROPERTY_LOCATION_TYPE_VALUE']
                ];
                $cache[$intElementID] = $arResult;
            } else {
                $cache[$intElementID] = false;
            }
        }

        return $cache[$intElementID];
    }

    /**
     * Возвращает массив типов локаций
     *
     * @return array
     *
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected static function getLocationTypes() {

        static $arLocationTypes = [];

        if (!empty($arLocationTypes)) {
            return $arLocationTypes;
        }

        $obCache = new \CPHPCache;
        if ($obCache->InitCache(86400, $cacheId = md5('LocationTypes'), $cacheDir = "/highload/LocationTypes/list/")) {
            $arLocationTypes = $obCache->GetVars();
        } elseif (Loader::includeModule('highloadblock') && $obCache->StartDataCache()) {

            $arHLIblockData = HL\HighloadBlockTable::getList([
                'filter' => ['NAME' => 'LocationTypes']
            ])->Fetch();
            if ($arHLIblockData) {
                $entity = HL\HighloadBlockTable::compileEntity($arHLIblockData)->getDataClass();
                $rsTypes = $entity::getList([
                    'order' => [
                        'UF_SORT' => 'ASC'
                    ],
                    'select' => [
                        'ID',
                        'NAME' => 'UF_NAME',
                        'CODE' => 'UF_XML_ID',
                        'SORT' => 'UF_SORT'
                    ]
                ]);
                while ($arType = $rsTypes->fetch()) {
                    $arLocationTypes[$arType['ID']] = $arType;
                }
            }

            global $CACHE_MANAGER;
            $CACHE_MANAGER->StartTagCache($cacheDir);
            $CACHE_MANAGER->RegisterTag("b_hlblock_" . 'LocationTypes');
            $CACHE_MANAGER->RegisterTag("b_hlblock_" . $arHLIblockData['ID']);
            $CACHE_MANAGER->EndTagCache();
            $obCache->EndDataCache($arLocationTypes);
        }

        return $arLocationTypes;
    }

    /**
     * Возвращает строку с правильным форматированием для отображения множественного свойства
     *
     * @param $arValues
     * @param string $arBanSym
     * @param string $arRepSym
     *
     * @return bool
     *
     * @throws \Bitrix\Main\LoaderException
     */
    public static function getValueForAutoCompleteMulti($arValues, $arBanSym = "", $arRepSym = "") {

        $arResult = false;

        if (is_array($arValues['VALUE'])) {
            foreach ($arValues['VALUE'] as $intPropertyValueID => $arOneValue) {
                if (!is_array($arOneValue)) {
                    $strTmp = $arOneValue;
                    $arOneValue = [
                        'VALUE' => $strTmp
                    ];
                }
                $mxResult = static::getPropertyValue($arOneValue);
                if (is_array($mxResult)) {
                    $arResult[$intPropertyValueID] = htmlspecialcharsbx(str_replace(
                        $arBanSym,
                        $arRepSym,
                        self::generateValueString($mxResult)
                    ));
                }
            }
        }

        return $arResult;
    }

    /**
     * Возвращает строку с правильным форматирование для отображения
     *
     * @param $arValue
     * @param string $arBanSym
     * @param string $arRepSym
     *
     * @return mixed|string
     *
     * @throws \Bitrix\Main\LoaderException
     */
    public static function getValueForAutoComplete($arValue, $arBanSym = "", $arRepSym = "") {

        $strResult = '';
        $mxResult = static::getPropertyValue($arValue);
        if (is_array($mxResult)) {
            $strResult = htmlspecialcharsbx(str_replace(
                $arBanSym,
                $arRepSym,
                self::generateValueString($mxResult)
            ));
        }

        return $strResult;
    }

    /**
     * Возвращает массив данных о значении свойства
     *
     * @param $arValue
     *
     * @return bool|mixed
     *
     * @throws \Bitrix\Main\LoaderException
     */
    protected static function getPropertyValue($arValue) {

        $mxResult = false;

        if ((int)$arValue['VALUE'] > 0) {
            $mxResult = static::getElementData($arValue['VALUE']);
        }

        return $mxResult;
    }

    /**
     * Возвращает строку для отображения в списках
     *
     * @param $mxResult
     *
     * @return string
     */
    public static function generateValueString($mxResult) {

        if (empty($mxResult['~NAME'])) {
            $mxResult['~NAME'] = $mxResult['NAME'];
        }

        $result = $mxResult['~NAME'] . ' [' . $mxResult['TYPE'] . ', '  . $mxResult['CODE'] . ', ' . $mxResult['ID'] . ']';

        return $result;
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
    function getPropertyFilterFieldHtml($arProperty, $strHTMLControlName) {

        global $APPLICATION;
        $arSettings = self::prepareSettings($arProperty);
        $arSymbols = static::getSymbols($arSettings);
        $isCityTypeAllowed = $arSettings['LOCATION_TYPES'] && in_array('city', $arSettings['LOCATION_TYPES']);
        $arFilteredLocationsTypes = $arProperty['USER_TYPE_SETTINGS']['LOCATION_TYPES'];
        if ($isCityTypeAllowed) {
            $arFilteredLocationsTypes[] = 'region';
        }

        /** Получим значения фильтра и из него возьмём заполненное значение нашего свойства */
        $filter = [];
        $filterOption = new Bitrix\Main\UI\Filter\Options($GLOBALS['sTableID']);
        $filterData = $filterOption->getFilter([]);
        foreach ($filterData as $k => $v) {
            $filter[$k] = $v;
        }
        $filterValue['VALUE'] = $filter['PROPERTY_' . $arProperty['ID']];

        /** Нужно для работы кастомного поля в UI фильтре */
        $result = '<input
                        type="hidden"
                        name="' . $strHTMLControlName["VALUE"] . '[]"
                        value=""
                        id="PROPERTY_' . $arProperty['ID'] . '_hidden"
                    />';
        if ($strHTMLControlName["DESCRIPTION"]) {
            $result .= '<input
                            type="hidden"
                            name="'.$strHTMLControlName["VALUE"].'_descr"
                            value=""
                            id="'.$strHTMLControlName["DESCRIPTION"].'_hidden"
                        />';
        }

        ob_start();
        $APPLICATION->IncludeComponent(
            "housage:location.lookup.input",
            "location_filter",
            [
                "CONTROL_ID" => 'PROPERTY_' . $arProperty['ID'] . '_rndm_' . mt_rand(0, 999999),
                "INPUT_NAME" => 'PROPERTY_' . $arProperty['ID'],
                "INPUT_NAME_STRING" => 'inp_PROP_' . $arProperty['ID'] . '][n0]',
                "INPUT_VALUE" => static::getPropertyValueString($filterValue),
                "START_TEXT" => Loc::getMessage('IBLOCK_CUSTOM_PROP_LOCATION__START_TEXT'),
                "MULTIPLE" => 'N',
                "IBLOCK_ID" => IBLOCK_COUNTRIES,
                "ACTIVE_FILTER" => $arProperty['USER_TYPE_SETTINGS']['LOCATION_ACTIVE'],
                "LOCATION_TYPE_FILTER" => serialize($arFilteredLocationsTypes),
                'WITHOUT_IBLOCK' => 'N',
                'BAN_SYM' => $arSymbols['BAN_SYM_STRING'],
                'REP_SYM' => $arSymbols['REP_SYM_STRING'],
                'SEARCH_REGION' => $arProperty['CODE'] == 'city' ? 'Y' : 'N',
                'FILTER' => 'Y',
                'FILTER_FIELDS' => [
                    'PROPERTY_NAME_ES',
                    'PROPERTY_NAME_EN'
                ]
            ],
            null,
            ["HIDE_ICONS" => "Y"]
        );
        $result .= ob_get_contents();
        ob_end_clean();

        return $result;

    }
}
