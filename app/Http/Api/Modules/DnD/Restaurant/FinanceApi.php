<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Transaction;
use App\Service\DnD\Restaurant\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class FinanceApi extends AbstractApi
{
    use ResolvesRestaurant;

    public function __construct(private readonly FinanceService $financeService)
    {
    }

    /**
     * Lists the restaurant's transactions, the most recent first
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The transactions in JSON format
     */
    public function getTransactions(string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        return $this->respond(Response::HTTP_OK, 'Transactions retrieved', [
            'transactions' => $this->financeService->getTransactions($restaurant),
        ]);
    }

    /**
     * Adds a transaction to the log by hand, paying its amount into the restaurant, or taking it out when negative
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the transaction and the updated transactions in JSON format
     */
    public function createTransaction(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            // In bits, negative when money goes out
            'amount'      => 'required|numeric|not_in:0|min:-99999999|max:99999999',
            'description' => 'nullable|string|max:65535',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $description = trim($request->get('description') ?? '');

        try {
            $transaction = $this->financeService->addTransaction($restaurant, trim($request->get('title')),
                (float) $request->get('amount'), $description !== '' ? $description : null);
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->respond(Response::HTTP_CREATED, 'Transaction added', [
            'uuid' => $transaction->uuid,
            'transactions' => $this->financeService->getTransactions($restaurant),
        ]);
    }

    /**
     * Gets the details of a transaction, such as the breakdown of a day's finances
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $transaction_uuid The uuid of the transaction
     * @return JsonResponse The transaction in JSON format
     */
    public function getTransaction(string $restaurant_uuid, string $transaction_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $transaction = Transaction::where('uuid', $transaction_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($transaction === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Transaction not found');
        }

        return $this->respond(Response::HTTP_OK, 'Transaction retrieved', $this->financeService->getDetails($transaction));
    }
}
