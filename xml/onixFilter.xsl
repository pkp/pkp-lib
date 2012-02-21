<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet
	version="1.0"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
	xmlns:oai="http://www.openarchives.org/OAI/2.0/"
	xmlns:xs="http://www.w3.org/2001/XMLSchema"
>
<xsl:param name="listName" /><!-- this is handed in via XSLTransformer->setParameters() -->
<xsl:output method="xml"/>

	<xsl:template match="/">
		<xs:simpleType><xsl:attribute name="name"><xsl:value-of select="$listName" /></xsl:attribute>
			<xsl:for-each select="//xs:simpleType[@name=$listName]/xs:restriction/xs:enumeration">
				<xsl:variable name="value"><xsl:value-of select="@value" /></xsl:variable>
				<xsl:choose>
					<!---
						Lists not specifically referenced in a <xsl:when> block will be processed with 
						all of their values included in the returned codelist. To filter a list, just 
						create a new <xsl:when></xsl:when> block with a test for the list name, and then 
						define the test you want to use. 
					-->

					<xsl:when test="$listName='List7'"> <!--  ONIX list for formats -->
						<xsl:if test="@value = 'AA' or @value = 'BC' or @value = 'BB' or @value = 'DA' or @value = 'EA'">
							<xsl:call-template name="onixFilterOutput" />
						</xsl:if>
					</xsl:when>

					<xsl:otherwise> <!-- define a case for all lists that are not filtered (yet) -->
						<xsl:call-template name="onixFilterOutput" />
					</xsl:otherwise>
				</xsl:choose>
			</xsl:for-each>
		</xs:simpleType>
	</xsl:template>

	<!-- recreate the ONIX node with the appropriate content.  Note: this removes the extraneous xs:documentation element -->
	<xsl:template name="onixFilterOutput">
		<xs:enumeration><xsl:attribute name="value"><xsl:value-of select="@value"/></xsl:attribute>
			<xs:documentation>
				<xsl:value-of select="xs:annotation/xs:documentation[position()=1]"/>
			</xs:documentation>
		</xs:enumeration>
	</xsl:template>
</xsl:stylesheet>
