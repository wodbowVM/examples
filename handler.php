<?php

namespace Sale\Handlers\PaySystem;

use \Bitrix\Main\Request,
    \Bitrix\Sale\Payment,
    \Bitrix\Sale\PaySystem,
    \Bitrix\Main\Localization\Loc,
    \Bitrix\Sale\PriceMaths;

Loc::loadMessages(__FILE__);

/**
 * Class LuckyTinkoffCreditHandler
 * @package Sale\Handlers\PaySystem
 */
class LuckyTinkoffCreditHandler extends PaySystem\ServiceHandler
{
    /**
     * Основная операция
     *
     * @param Payment $payment
     * @param Request|null $request
     *
     * @return PaySystem\ServiceResult
     *
     * @throws \Bitrix\Main\ArgumentNullException
     */
    public function initiatePay(Payment $payment, Request $request = null)
    {
        $paymentShouldPay = (float) PriceMaths::roundPrecision($this->getBusinessValue($payment, 'PAYMENT_SHOULD_PAY'));

        $items = $this->getOriginalItems($payment, $paymentShouldPay, $this);

        /**
         * пересчёт массива товаров с ценами с учётом использования бонусных баллов
         */
        $items = $this->correctItems($items, $paymentShouldPay);

        $params = $this->generateReceipt($payment, $items, $paymentShouldPay);

        $this->setExtraParams($params);

        return $this->showTemplate($payment, "template");
    }

    /**
     * Возвращает полный список товаров в корзине
     *
     * @param $payment
     * @param $paymentShouldPay
     * @param $handler
     *
     * @return array
     *
     * @throws \Bitrix\Main\ArgumentNullException
     */
    public function getOriginalItems($payment, $paymentShouldPay, $handler) {
        $items = [];
        $shipmentCollection = $payment->getCollection()->getOrder()->getShipmentCollection();

        foreach ($shipmentCollection as $shipmentItem) {

            $shipmentItemCollection = $shipmentItem->getShipmentItemCollection();

            foreach ($shipmentItemCollection as $elem) {

                $basketItem = $elem->getBasketItem();

                if ($basketItem->isBundleChild()) {
                    continue;
                }

                if (!$basketItem->getFinalPrice()) {
                    continue;
                }

                $items[] = [
                    'name' => substr($basketItem->getField('NAME'), 0, 64),
                    'quantity' => $elem->getQuantity(),
                    'sum' => round($basketItem->getField('PRICE'), 2)
                ];
            }

            if (!$shipmentItem->isSystem() && $shipmentItem->getPrice()) {
                $items[] = [
                    'name' => substr($shipmentItem->getDeliveryName(), 0, 64),
                    'quantity' => 1,
                    'sum' => \Bitrix\Sale\PriceMaths::roundPrecision(
                        $shipmentItem->getPrice()
                    ),
                    'delivery' => true  // пометим что это доставка, для того, чтобы не производить пересчёт цены доставки
                ];
            }
        }

        if (empty($items)) {
            $items[] = [
                'name' => Loc::getMessage('LUCKY_TINKOFF_CREDIT.CATEGORY_NAME'),
                'quantity' => 1,
                'sum' => $paymentShouldPay
            ];
        }

        return $items;
    }

    /**
     * Удаляет товары с отрицательной суммой, распределяя эту скидку равномерно по остальным товарам
     *
     * @param $items
     * @param $paymentShouldPay
     *
     * @return array|bool
     */
    public static function correctItems($items, $paymentShouldPay)
    {
        if (empty($items)) {
            return false;
        }

        // Объявим переменные
        $discountSum = 0;   // Будем хранить величину скидки за бонусные баллы
        $paymentFullSum = 0; // Общая сумма чека, без учётка скидок
        // Сумма товаров за вычетом доставки без применения скидок (это та сумма, которую нужно раскидать)
        $paymentFullSumWithoutDelivery = 0;
        $paymentRealSum = 0; // Сумма, которую нужно реально заплатить
        $deliveryKey = false;

        // Посчитаем общую стоимость чека и сумму без учёта доставок
        foreach ($items as $key => $item) {
            if ($item['sum'] > 0) {
                // Доставку пересчитывать не будем
                // Преобразуем строки с запятыми в числа с точкой, например 382,90 -> 382.90
                $item['sum'] = str_replace(",", ".", $item['sum']);
                $item['sum'] = round(floatval($item['sum']), 2);
                $items[$key]['sum'] = $item['sum'];
                if ($item['delivery'] !== true) {
                    $paymentFullSumWithoutDelivery += $item['sum'] * $item['quantity'];
                } else {
                    $deliveryKey = $key;
                }
                $paymentFullSum += $item['sum'] * $item['quantity'];
            } else {
                $discountSum += $item['sum'] * $item['quantity'];
                unset($items[$key]);
            }
        }

        // Высчитаем реальную сумму платежа
        $paymentRealSum = $paymentFullSum + $discountSum;

        if ($paymentShouldPay !== $paymentRealSum) {
            $discountSum += $paymentShouldPay - $paymentRealSum;
        }

        // Если нет товаров с минусом - возвращаем массив без пересчётов
        if (empty($discountSum)) {
            return $items;
        }

        $balanceSum = -$discountSum;   // Из этой переменной будем вычитать скидки

        // Будем искать товар с максимальной ценой
        $keyItemMaxSum = false;
        $maxSum = 0;

        // Рассчитаем скидки и вычтем их из товаров
        foreach ($items as $key => $item) {
            // Доставку пересчитывать не будем, поскольку бонусные баллы не распространяются на доставку
            if ($item['delivery'] !== true) {
                // Округляем до копейки
                $discount = round($discountSum * ($item['sum'] / $paymentFullSumWithoutDelivery), 2);

                // Если товара больше 1, округлим в меньшую сторону до кратности
                if ($item['quantity'] > 1) {
                    $num = ceil($discount * 100 / $item['quantity']);
                    $discount = $num * $item['quantity'] / 100;
                }

                $items[$key]['sum'] = round($items[$key]['sum'] + $discount, 2);
                // В этой переменной высчитываем остатки по скидке за бонусные баллы
                // После всех рассчётов эта переменная часто не нулевая
                $balanceSum += $discount;
                // Найдём товар с максимальной ценой и запомним его индекс (учитываем только товары в количестве 1 штука)
                if ($items[$key]['sum'] > $maxSum && $item['quantity'] == 1) {
                    $keyItemMaxSum = $key;
                    $maxSum = $items[$key]['sum'];
                }
            }
        }

        // Проведём проверку заново, сосчитаем сумму, которую должен заплатить пользователь и сверим с рассчётной
        $paymentFullSumAfterCorrect = 0;
        foreach ($items as $key => $item) {
            $paymentFullSumAfterCorrect += $item['sum'] * $item['quantity'];
        }

        // Если суммы совпали, значит всё хорошо, если нет, то разницу вычтем из товара с самой высокой ценой
        if ($paymentFullSumAfterCorrect !== $paymentShouldPay) {
            if ($keyItemMaxSum !== false) {
                $items[$keyItemMaxSum]['sum'] -= $paymentFullSumAfterCorrect - $paymentShouldPay;
                $items[$keyItemMaxSum]['sum'] = round($items[$keyItemMaxSum]['sum'], 2);
            } else {
                // Поскольку не нашлось товара в корзине с количеством 1, нам неоткуда делать вычитание погрешности, вычтем копейки из доставки
                // Решение спорное, но выхода не вижу
                if ($deliveryKey !== false) {
                    $items[$deliveryKey]['sum'] -= $paymentFullSumAfterCorrect - $paymentShouldPay;
                    $items[$deliveryKey]['sum'] = round($items[$deliveryKey]['sum'], 2);
                } else {
                    // Есть погрешность в рассчётах, нет товаров в количестве 1 штука и при этом бесплатная доставка
                    \CEventLog::Add([
                        'SEVERITY' => 'INFO',
                        'AUDIT_TYPE_ID' => 'ROBOKASSA_EXCEPTION',
                        'ITEM_ID' => 'Robokassa',
                        'MODULE_ID' => 'lucky.robokassa',
                        'DESCRIPTION' => 'Robokassa exception: НЕ СМОГЛИ отнять сумму ' . ($paymentFullSumAfterCorrect - $paymentShouldPay) . ' из стоимости доставки, поскольку доставка бесплатная'
                    ]);
                }
            }
        }

        // Обновим индексы массивов
        $items = array_values($items);

        return $items;
    }

    /**
     * Возвращает идентификатор оплаты (не заказа!) из $request при возврате информации ПС на сайт
     *
     * @param Request $request
     *
     * @return integer
     */
    public function getPaymentIdFromRequest(Request $request)
    {
        $paymentId = $request->get('ORDER');
        $paymentId = preg_replace("/^[0]+/","",$paymentId);
        $paymentId = intval($paymentId);

        return $paymentId;
    }

    /**
     * Возвращает массив со списком валют
     *
     * @return array
     */
    public function getCurrencyList()
    {
        return ['RUB'];
    }

    /**
     * Выполняет обработку результата от платежной системы, нужна обязательно
     *
     * @param Payment $payment
     * @param Request $request
     */
    public function processRequest(Payment $payment, Request $request)
    {
        /** Не используется */
    }

    /**
     * Формирует массив данных для подстановки в форму и передачи в банк
     *
     * @param $payment
     *
     * @return array
     */
    private function generateReceipt($payment, $items, $paymentShouldPay) {
        $arReceipt = [];

        $order = $payment->getOrder();
        $propertyCollection = $order->getPropertyCollection();
        $arReceipt['SUM'] = $paymentShouldPay;
        $arReceipt['EMAIL'] = $propertyCollection->getUserEmail()->getValue();
        $arReceipt['PHONE'] = $propertyCollection->getPhone()->getValue();
        $arReceipt['ORDER_ID'] = $order->getId();
        $arReceipt['USER_ID'] = $order->getUserId();

        foreach ($items as $basketItem) {
            $arReceipt['ITEMS'][] = [
                'NAME' => $basketItem['name'],
                'QUANTITY' => $basketItem['quantity'],
                'PRICE' => $basketItem['sum'],
                'CATEGORY' => Loc::getMessage('LUCKY_TINKOFF_CREDIT.CATEGORY_NAME')
            ];
        }

        return $arReceipt;
    }
}
