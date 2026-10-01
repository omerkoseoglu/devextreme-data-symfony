<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony;

use DevExtreme\Data\ArraySource;
use DevExtreme\Data\Contracts\DataSourceInterface;
use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\LoadResult;
use DevExtreme\Data\Symfony\Doctrine\DbalSource;
use Doctrine\DBAL\Query\QueryBuilder as DbalQueryBuilder;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The `devextreme_data.loader` service: turns a request into a DevExtreme answer.
 */
final class DevExtremeLoader
{
    public function __construct(
        private readonly RequestStack $requests,
        private readonly bool $normalizeDates = true,
        private readonly ?int $maxTake = null,
    ) {
    }

    /**
     * Parses the DevExtreme parameters of a request (query string, form fields or a JSON body).
     *
     * @throws BadRequestHttpException when a parameter is malformed
     */
    public function options(?Request $request = null): LoadOptions
    {
        $request ??= $this->requests->getCurrentRequest()
            ?? throw new LogicException('There is no current request; pass one explicitly.');

        $params = $request->query->all() + $request->request->all();

        if ($request->getContentTypeFormat() === 'json' && ($content = $request->getContent()) !== '') {
            $json = json_decode($content, true);
            if (!is_array($json)) {
                throw new BadRequestHttpException('The request body is not valid JSON.');
            }
            $params = $json + $params;
        }

        try {
            $options = LoadOptions::fromArray($params);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        if ($this->maxTake !== null && !$options->isCountQuery && !$options->isSummaryQuery
            && ($options->take < 1 || $options->take > $this->maxTake)) {
            $options->take = $this->maxTake;
        }

        return $options;
    }

    /**
     * @param iterable<mixed>|DataSourceInterface $source an array/iterable, a data source such as
     *                                                    {@see DbalSource} or {@see Doctrine\EntitySource}
     *
     * @throws BadRequestHttpException when the request is malformed (rendered by Symfony as HTTP 400)
     */
    public function load(iterable|DataSourceInterface $source, ?Request $request = null): LoadResult
    {
        $options = $this->options($request);

        try {
            return $this->source($source)->load($options);
        } catch (InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }
    }

    public function response(iterable|DataSourceInterface $source, ?Request $request = null): JsonResponse
    {
        return new JsonResponse($this->load($source, $request));
    }

    /**
     * @param iterable<mixed>|DataSourceInterface|DbalQueryBuilder $source
     */
    public function source(mixed $source): DataSourceInterface
    {
        return match (true) {
            $source instanceof DataSourceInterface => $source,
            is_iterable($source) => new ArraySource($source),
            default => throw new LogicException('Unsupported data source: ' . get_debug_type($source)
                . '. Wrap Doctrine queries with DbalSource::forQueryBuilder() or EntitySource::for().'),
        };
    }

    public function normalizeDates(): bool
    {
        return $this->normalizeDates;
    }
}
