<?php
class Payment
{
    /**
     * payFromUserBalance - производит оплату существующего заказа с баланса пользователя
     * (если у него есть средства на внутреннем счете)
     *
     * При просмотре заказа, есть кнопка оплатить с внутреннего счета,
     * при нажатии на нее отправляется ajax запрос к данному методу,
     *
     * если вернулось true - пользователь видит что оплата успешная и получает сообщение в
    телеграм
     * если вернулось false - пользователь видит сообщение "Недостаточно средств на вашем
    счете"
     *
     * дополнительно:
     * User и Order - стандартные модели для работы с бд (внутрь них не надо
    проваливаться)
     * $request->get($key) - возвращает $_GET[$key]
     * NotificationSender - отправляет сообщения (в его реализацию тоже не проваливаемся)
     */
    public function payFromUserBalance(Request $request)
    {
        $userId = $request->get('user_id');
        $orderId = $request->get('order_id');
        $sum = (float)$request->get('sum');
        $user = User::getUserById($userId);
        $userBalance = $user->balance;
        $user->balance -= $sum;
        $order = Order::getOrderById($orderId);
// при успешной оплате отправляем сообщение в телеграм пользователю
        if($sum <= $userBalance && $sum === $order->sum) {
            $order->status = Order::STATUS_PAID;
            (new NotificationSender())->sendTelegramMessage($user->id, 'Заказ # ' . $order->id .
                ' успешно оплачен!');
        } else {
            return false;
        }
        $user->save();
        $order->save();
        return true;
    }
}
