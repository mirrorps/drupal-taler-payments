<?php

declare(strict_types=1);

namespace Drupal\taler_payments\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\taler_payments\Checkout\TalerCheckoutManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Handles checkout start and checkout page rendering.
 */
final class TalerCheckoutController extends ControllerBase {

  public function __construct(
    private readonly TalerCheckoutManagerInterface $checkoutManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $manager = $container->get('taler_payments.checkout_manager');
    if (!$manager instanceof TalerCheckoutManagerInterface) {
      throw new \InvalidArgumentException('Service taler_payments.checkout_manager must implement TalerCheckoutManagerInterface.');
    }

    return new static($manager);
  }

  /**
   * Creates or reuses checkout order and redirects to checkout page.
   */
  public function start(Request $request): RedirectResponse {
    $title = trim((string) $request->query->get('title', ''));
    $amount = trim((string) $request->query->get('amount', ''));
    $summary = trim((string) $request->query->get('summary', ''));

    if ($amount === '') {
      throw new AccessDeniedHttpException('Invalid checkout request.');
    }

    $intent = $this->checkoutManager->beginCheckout($title, $amount, $summary);

    return new RedirectResponse(
      Url::fromRoute('taler_payments.checkout_page', [
        'order_id' => (string) $intent['order_id'],
      ])->toString()
    );
  }

  /**
   * Builds checkout page with payment details and wallet action.
   */
  public function checkout(string $order_id): array {
    $checkout = $this->checkoutManager->getCheckoutByOrderId($order_id);
    if ($checkout === NULL) {
      throw new AccessDeniedHttpException('Checkout page is unavailable.');
    }

    $status = (string) ($checkout['status'] ?? 'unknown');
    $summary = (string) ($checkout['summary'] ?? '');
    $amount = (string) ($checkout['amount'] ?? '');
    $pay_uri = (string) ($checkout['taler_pay_uri'] ?? '');

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['taler-checkout-page'],
      ],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Review your order details and complete payment with GNU Taler.'),
        '#attributes' => [
          'class' => ['taler-checkout-intro'],
        ],
      ],
      'meta' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['taler-checkout-meta']],
        'summary' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['taler-checkout-meta-row']],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $this->t('Summary'),
            '#attributes' => ['class' => ['taler-checkout-meta-label']],
          ],
          'value' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $summary,
            '#attributes' => ['class' => ['taler-checkout-meta-value']],
          ],
        ],
        'amount' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['taler-checkout-meta-row']],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $this->t('Amount'),
            '#attributes' => ['class' => ['taler-checkout-meta-label']],
          ],
          'value' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $amount,
            '#attributes' => ['class' => ['taler-checkout-meta-value', 'taler-checkout-meta-value-amount']],
          ],
        ],
        'order_id' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['taler-checkout-meta-row']],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $this->t('Order ID'),
            '#attributes' => ['class' => ['taler-checkout-meta-label']],
          ],
          'value' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $order_id,
            '#attributes' => ['class' => ['taler-checkout-meta-value', 'taler-checkout-meta-value-order-id']],
          ],
        ],
      ],
    ];

    if ($status === 'paid') {
      $already_paid_order_id = trim((string) ($checkout['already_paid_order_id'] ?? ''));
      $message = $already_paid_order_id !== ''
        ? $this->t('Payment already completed for related order @order_id.', ['@order_id' => $already_paid_order_id])
        : $this->t('Payment already completed.');
      $build['status'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $message,
        '#attributes' => [
          'class' => ['taler-checkout-status', 'taler-checkout-status-success'],
          'data-taler-status' => $status,
        ],
      ];
    }
    elseif ($status === 'unpaid' && $pay_uri !== '') {
      $build['status'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Payment pending. Continue in your Taler wallet.'),
        '#attributes' => [
          'class' => ['taler-checkout-status', 'taler-checkout-status-pending'],
          'data-taler-status' => $status,
        ],
      ];
      if (str_starts_with($pay_uri, 'taler://')) {
        $build['pay_link'] = [
          '#type' => 'html_tag',
          '#tag' => 'a',
          '#value' => $this->t('Pay with Taler wallet in the browser'),
          '#attributes' => [
            'href' => $pay_uri,
            'class' => ['button', 'button--primary', 'taler-checkout-pay-link'],
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
          ],
        ];
        $build['wallet_hint'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['taler-checkout-wallet-hint']],
          'intro' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $this->t('To pay in the browser, the Taler Wallet extension must be installed. '),
          ],
          'wallet_link' => [
            '#type' => 'link',
            '#title' => $this->t('Get the wallet'),
            '#url' => Url::fromUri('https://www.taler.net/en/wallet.html'),
            '#attributes' => [
              'target' => '_blank',
              'rel' => 'noopener noreferrer',
            ],
          ],
        ];
      }
      else {
        $build['status_invalid_uri'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Wallet URI is invalid. Please refresh and try again.'),
          '#attributes' => [
            'class' => ['taler-checkout-status', 'taler-checkout-status-warning'],
          ],
        ];
      }
    }
    elseif ($status === 'not_found') {
      $build['status'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('This checkout order is no longer available. Please go back and start a new payment.'),
        '#attributes' => [
          'class' => ['taler-checkout-status', 'taler-checkout-status-warning'],
          'data-taler-status' => $status,
        ],
      ];
    }
    else {
      $build['status'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Order status is currently unavailable. Please retry.'),
        '#attributes' => [
          'class' => ['taler-checkout-status', 'taler-checkout-status-warning'],
          'data-taler-status' => $status,
        ],
      ];
    }

    $build['#attached']['library'][] = 'taler_payments/payment_button';
    $build['#attached']['drupalSettings']['talerPaymentsCheckout'] = [
      'orderId' => $order_id,
      'statusUrl' => Url::fromRoute('taler_payments.checkout_status', ['order_id' => $order_id])->toString(),
      'pollIntervalMs' => 3000,
    ];
    $build['#attached']['html_head'][] = [
      [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'taler-support',
          'content' => 'uri,api,hijack',
        ],
      ],
      'taler_payments_taler_support_meta',
    ];
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * Returns current checkout status as JSON for polling.
   */
  public function status(string $order_id): JsonResponse {
    $checkout = $this->checkoutManager->getCheckoutByOrderId($order_id);
    if ($checkout === NULL) {
      return new JsonResponse([
        'status' => 'not_found',
        'is_final' => TRUE,
      ]);
    }

    $status = (string) ($checkout['status'] ?? 'unknown');
    $is_final = in_array($status, ['paid', 'not_found', 'cancelled'], TRUE);

    return new JsonResponse([
      'status' => $status,
      'is_final' => $is_final,
    ]);
  }

  /**
   * Builds dynamic checkout page title.
   */
  public function checkoutTitle(string $order_id): string {
    $checkout = $this->checkoutManager->getCheckoutByOrderId($order_id);
    if ($checkout === NULL) {
      return (string) $this->t('Checkout');
    }

    $title = trim((string) ($checkout['title'] ?? ''));
    return $title !== '' ? $title : (string) $this->t('Checkout');
  }

}
