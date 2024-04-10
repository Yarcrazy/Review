<?php

interface PaymentInterface {
    public function pay(float $sum):bool;
}

abstract class PaymentAbstract implements PaymentInterface {
    private User $user;
    private Order $order;
    public function __construct(User $user, Order $order)
    {
        $this->user = $user;
        $this->order = $order;
    }

    public function getUser() {
        return $this->user;
    }

    public function getOrder() {
        return $this->order;
    }
}

class PaymentService {
    private PaymentAbstract $source;

    public function __construct(PaymentAbstract $source)
    {
        $this->source = $source;
    }

    public function paymentProcess(float $sum):void {
        if ($this->source->pay($sum)) {
            $this->afterPay();
        }
    }

    private function afterPay() {
        // в Notification не проваливаюсь, но тут тоже бы использовал приватное свойство и инит в конструкторе
        (new NotificationSender())->sendTelegramMessage($this->source->getUser()->id, 'Заказ # ' . $this->source->getOrder()->id .
            ' успешно оплачен!');
    }
}

class BalancePayment extends PaymentAbstract implements PaymentInterface
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
    public function pay(float $sum):bool
    {
        if ($sum <= $this->user->balance && $sum === $this->order->sum) {
            $this->user->balance -= $sum;
            $this->order->status = Order::STATUS_PAID;
            $this->user->save();
            $this->order->save();
        } else {
            //лог
            return false;
        }
        return true;
    }
}

try {
    $userId = $request->get('user_id');
    $orderId = $request->get('order_id');
    $sum = (float)$request->get('sum');
    if ($user = User::getUserById($userId) && $order = Order::getOrderById($orderId) && $sum > 0) {
        $balance = new BalancePayment($user, $order);
        $service = (new PaymentService($balance))->paymentProcess($sum);
        return json_encode([
            'status' => 'ok'
        ]);
    } else {
        throw new Exception('Отсутствуют необходимые данные');
    }
} catch (Exception $exception) {
    //лог
    return json_encode([
        'status' => 'error',
        'message' => $exception->getMessage(),
    ]);
}
