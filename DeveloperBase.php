<?php
namespace Housage\Catalog;

use \Bitrix\Main,
    \Bitrix\Main\Loader,
    \Bitrix\Highloadblock as HL;

class DeveloperBase
{
    /** @var string */
    const TABLE_NAME = 'CatalogDevelopers';

    /** @var string Папка раздела Застройщики */
    const DIRECTORY = '/promotores-inmobiliarios/';

    /** @var array  */
    protected static $hlEntity = [];

    /**
     * Возвращает массив информации о застройщике по его внутреннему ID
     *
     * @param $internalId
     *
     * @return mixed
     *
     * @throws Main\ArgumentException
     * @throws Main\LoaderException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    public static function getByInternalId($internalId)
    {
        static $arValues = [];

        if (empty($arValues[$internalId])) {
            $arList = static::getList();

            foreach ($arList as $arItem) {
                if ($arItem['ID'] == $internalId) {
                    $arValues[$internalId] = $arItem;
                    break;
                }
            }
        }

        return $arValues[$internalId];
    }

    /**
     * Возвращает массив информации о застройщике по его XML_ID
     *
     * @param $xmlId
     *
     * @return mixed
     *
     * @throws Main\ArgumentException
     * @throws Main\LoaderException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    public static function getByXmlId($xmlId)
    {
        static $arValues = [];

        if (empty($arValues[$xmlId])) {
            $arList = static::getList();

            foreach ($arList as $arItem) {
                if ($arItem['XML_ID'] == $xmlId) {
                    $arValues[$xmlId] = $arItem;
                    break;
                }
            }
        }

        return $arValues[$xmlId];
    }

    /**
     * @param $xmlId
     *
     * @return string
     *
     * @throws Main\ArgumentException
     * @throws Main\LoaderException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    public static function getDetailPageUrlByXmlId($xmlId)
    {
        $arDeveloper = self::getByXmlId($xmlId);

        if (empty($arDeveloper['CODE'])) {
            return '';
        }

        return self::DIRECTORY . $arDeveloper['CODE'] . '/';
    }

    /**
     * Возвращает массив данных о застройщике по имени
     *
     * @param $name
     *
     * @return mixed
     *
     * @throws Main\ArgumentException
     * @throws Main\LoaderException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    public static function getByName($name)
    {
        $xmlId = self::getXmlIdByName($name);
        return self::getByXmlId($xmlId);
    }

    /**
     * Формирует и возвращает символьный код по нованию
     *
     * @param $name
     *
     * @return mixed
     */
    public static function getCodeByName($name)
    {
        return \CUtil::translit(trim($name), 'es', ['replace_other' => '-', 'change_case' => 'L', 'replace_space' => '-']);
    }

    /**
     * Формирует и возвращает XML_ID по нованию
     *
     * @param $name
     *
     * @return mixed
     */
    public static function getXmlIdByName($name)
    {
        return self::getCodeByName($name);
    }

    /**
     * Возвращает массив всех затройщиков
     *
     * @return array
     *
     * @throws Main\ArgumentException
     * @throws Main\LoaderException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    public static function getList()
    {
        static $arList = [];

        if (empty($arList)) {
            $arList = self::getBaseList();
        }

        return $arList;
    }

    /**
     * @param string $tableName
     *
     * @return array
     *
     * @throws Main\ArgumentException
     * @throws Main\LoaderException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    protected static function getBaseList($tableName = self::TABLE_NAME)
    {
        $arList = [];

        if (Loader::includeModule('highloadblock')) {

            $entityClass = self::getHlEntity($tableName)->getDataClass();
            $rsList = $entityClass::getList([
                'order' => [
                    'ID' => 'ASC'
                ],
                'cache' => [
                    'ttl' => 86400
                ]
            ]);

            while ($arItem = $rsList->fetch()) {

                $arItem = static::prepareData($arItem);

                if ($arItem['XML_ID']) {
                    $arList[$arItem['XML_ID']] = $arItem;
                }
            }
        }

        return $arList;
    }

    /**
     * УБирает проефиксы и подгружает файл LOGO
     *
     * @param $arItem
     *
     * @return array
     */
    public static function prepareData($arItem)
    {
        /** Исключаем UF_ префиксы */
        $tmpItem = [];
        foreach ($arItem as $key => $value) {
            $tmpItem[deleteUfPrefix($key)] = $value;
        }
        $arItem = $tmpItem;
        unset($tmpItem);

        /** Получим изображение */
        if (!empty($arItem['LOGO'])) {
            $arItem['LOGO'] = \CFile::GetFileArray($arItem['LOGO']);
        }
        elseif (!empty($arItem['FILE'])) {
            $arItem['LOGO'] = \CFile::GetFileArray($arItem['FILE']);
        }

        /** Обработка описания */
        $arItem['~ABOUT'] = $arItem['ABOUT'];
        if ($arItem['ABOUT'] == strip_tags($arItem['ABOUT'])) {
            $arItem['ABOUT'] = TxtToHTML($arItem['ABOUT']);
        }

        return $arItem;
    }


    /**
     * Добавляет запись в справочник
     *
     * @param array $arData
     *
     * @return bool
     *
     * @throws Main\ArgumentException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    public static function add($arData)
    {
        $arFields = [];

        if (empty($arData['XML_ID'])) {
            $arData['XML_ID'] = self::getCodeByName($arData['NAME']);
        }
        if (empty($arData['CODE'])) {
            $arData['CODE'] = self::getCodeByName($arData['NAME']);
        }

        foreach ($arData as $key => $value) {
            $arFields[addUfPrefix($key)] = $value;
        }

        $entity = self::getHlEntity();
        $hlEntityDataClass = $entity->getDataClass();
        $result = $hlEntityDataClass::add($arFields);

        if ($result->isSuccess()) {
            $entity->cleanCache();
            return true;
        }

        return false;
    }

    /**
     * Обновляет запись о застройщике
     *
     * @param int $elementId
     * @param array $arData
     *
     * @return bool
     *
     * @throws Main\ArgumentException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    public static function update($elementId, $arData = [])
    {
        $arFields = [];

        if ($arData['NAME']) {
            $arData['XML_ID'] = self::getCodeByName($arData['NAME']);
            $arData['CODE'] = self::getCodeByName($arData['NAME']);
        }

        foreach ($arData as $key => $value) {
            $arFields[addUfPrefix($key)] = $value;
        }

        $entity = self::getHlEntity();
        $hlEntityDataClass = $entity->getDataClass();
        $result = $hlEntityDataClass::update(
            $elementId,
            $arFields
        );

        if ($result->isSuccess()) {
            $entity->cleanCache();
            return true;
        }

        return false;
    }

    /**
     * Возвращает объект класса для HL, используемого под хранение данных застройщиков
     *
     * @param string $tableName
     *
     * @return Main\Entity\Base
     *
     * @throws Main\ArgumentException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    public static function getHlEntity($tableName = self::TABLE_NAME)
    {
        if (empty(self::$hlEntity[$tableName])) {
            $entity = HL\HighloadBlockTable::compileEntity(
                HL\HighloadBlockTable::getList(
                    ['filter' => ['NAME' => $tableName]]
                )->fetch()
            );
            self::$hlEntity[$tableName] = $entity;
        }

        return self::$hlEntity[$tableName];
    }
}
