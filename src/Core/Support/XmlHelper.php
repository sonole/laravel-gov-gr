<?php

namespace Sonole\GovGr\Core\Support;

use SimpleXMLElement;

class XmlHelper
{
    /** Escapes a value for safe embedding as XML element text content. */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Always renders the tag, escaped. Use for fields that are guaranteed non-null. */
    public static function tag(string $name, string $value): string
    {
        return "<$name>" . self::escape($value) . "</$name>";
    }

    /** Renders the tag even if $value is null (empty content). Matches WSDL samples that always include a field, blank or not. */
    public static function tagOrEmpty(string $name, ?string $value): string
    {
        return "<$name>" . ($value !== null ? self::escape($value) : '') . "</$name>";
    }

    /** Omits the tag entirely when $value is null/empty. Use for genuinely optional fields. */
    public static function optionalTag(string $name, ?string $value): string
    {
        return $value === null || $value === '' ? '' : self::tag($name, $value);
    }

    /** @return array<string, mixed>|null */
    public static function toArray(string $xml): ?array
    {
        $plain = self::stripNamespacePrefixes(trim($xml));

        if (empty($plain)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $element = simplexml_load_string($plain, SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($element === false) {
            return null;
        }

        $encoded = json_encode($element);

        return $encoded === false ? null : json_decode($encoded, true);
    }

    /** Non-XML input (e.g. an HTML error page from a proxy/WAF) is expected here, not a bug. Returns null instead of letting libxml warnings surface as errors. */
    private static function stripNamespacePrefixes(string $xml): ?string
    {
        $previous = libxml_use_internal_errors(true);
        $obj = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($obj === false) {
            return null;
        }

        $namespaces = $obj->getNamespaces(true);

        if (empty($namespaces)) {
            return $xml;
        }

        foreach (array_keys($namespaces) as $prefix) {
            $replaced = preg_replace('#(</?' . preg_quote($prefix, '#') . ')(:)#', '$1_', $xml);

            if ($replaced === null) {
                return null;
            }

            $xml = $replaced;
        }

        return $xml;
    }
}
