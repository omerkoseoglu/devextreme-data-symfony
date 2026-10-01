<?php

declare(strict_types=1);

namespace DevExtreme\Data\Symfony;

use DevExtreme\Data\LoadOptions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class LoadOptionsValueResolver implements ValueResolverInterface
{
    public function __construct(private readonly DevExtremeLoader $loader)
    {
    }

    /**
     * @return iterable<LoadOptions>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== LoadOptions::class) {
            return [];
        }

        return [$this->loader->options($request)];
    }
}
