<?xml version="1.0" encoding="UTF-8"?>

<!--
  * xml/onixFilter.xsl
  *
  * Copyright (c) 2014-2024 Simon Fraser University
  * Copyright (c) 2000-2024 John Willinsky
  * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
  *
  * XSL-based filter to remove extraneous elements (e.g. Values in List 150) for use in OMP
  -->

<xsl:stylesheet
	version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
>
    <xsl:param name="listName" /><!-- this is handed in via XSLTransformer->setParameters() -->
    <xsl:output method="xml" />

        <xsl:template match="/">
            <CodeList>
                <CodeListNumber><xsl:value-of select="$listName"/></CodeListNumber>
                <xsl:for-each select="//CodeList[CodeListNumber=$listName]/Code">
                    <xsl:variable name="value" select="CodeValue"/>
                    <xsl:choose>
                        <!---
                            Lists not specifically referenced in a <xsl:when> block will be processed with
                            all of their values included in the returned codelist. To filter a list, just
                            create a new <xsl:when></xsl:when> block with a test for the list name, and then
                            define the test you want to use.
                        -->
                        <xsl:when test="DeprecatedNumber != ''"><!-- Deprecated ONIX codes -->
                            <xsl:call-template name="onixFilterDeprecated" />
                        </xsl:when>
                        <xsl:when test="$listName='55'"><!-- Don't include code number in dates -->
                            <xsl:call-template name="onixFilterOutputWithoutCode" />
                        </xsl:when>
                        <xsl:when test="$listName='150'"><!-- ONIX list for formats -->
                            <xsl:if test="$value = 'AA' or $value = 'BC' or $value = 'BB' or $value = 'DA' or $value = 'EA'">
                                <xsl:call-template name="onixFilterOutputWithCode" />
                            </xsl:if>
                        </xsl:when>
                        <xsl:otherwise> <!-- define a case for all lists that are not filtered (yet) -->
                            <xsl:call-template name="onixFilterOutputWithCode" />
                        </xsl:otherwise>
                    </xsl:choose>
                </xsl:for-each>
            </CodeList>
        </xsl:template>

        <!-- recreate the filtered ONIX node with the DeprecatedNumber included -->
        <xsl:template name="onixFilterDeprecated">
            <Code>
                <CodeValue><xsl:value-of select="CodeValue"/></CodeValue>
                <CodeDescription><xsl:value-of select="CodeDescription"/></CodeDescription>
                <DeprecatedNumber><xsl:value-of select="DeprecatedNumber"/></DeprecatedNumber>
            </Code>
        </xsl:template>

        <!-- recreate the filtered ONIX node with the Code Value in the description -->
        <xsl:template name="onixFilterOutputWithCode">
            <Code>
                <CodeValue><xsl:value-of select="CodeValue"/></CodeValue>
                <CodeDescription><xsl:value-of select="CodeDescription"/> (<xsl:value-of select="CodeValue"/>)</CodeDescription>
            </Code>
        </xsl:template>

        <!-- recreate the filtered ONIX node -->
        <xsl:template name="onixFilterOutputWithoutCode">
            <Code>
                <xsl:copy-of select="CodeValue"/>
                <xsl:copy-of select="CodeDescription"/>
            </Code>
        </xsl:template>

</xsl:stylesheet>
