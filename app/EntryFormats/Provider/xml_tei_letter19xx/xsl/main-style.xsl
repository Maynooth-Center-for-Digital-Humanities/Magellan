<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:TEI="http://www.tei-c.org/ns/1.0"
    xmlns:func="http://exslt.org/functions"
    extension-element-prefixes="func"
    version="1.0">

    <xsl:output method="xml" omit-xml-declaration="yes" indent="no" />
    <xsl:strip-space elements="*"/>

    <xsl:template match="*" mode="copy">
        <xsl:element name="{name()}">
            <xsl:copy-of select="@*"/>
            <xsl:apply-templates select="node()" mode="copy" />
        </xsl:element>
    </xsl:template>



    <xsl:template match="@*|text()|comment()"  mode="copy">
        <xsl:call-template name="string-replace-all">
            <xsl:with-param name="text"    select="."/>
            <xsl:with-param name="replace" select="'&#10;'"/>
            <xsl:with-param name="by"      select="''"/>
        </xsl:call-template>
    </xsl:template>

    <xsl:template match="@*|text()|comment()" mode="copy">
        <xsl:call-template name="replace-quotes">
            <xsl:with-param name="text" select="."/>
        </xsl:call-template>
    </xsl:template>

    <xsl:template name="string-replace-all">
        <xsl:param name="text"/>
        <xsl:param name="replace"/>
        <xsl:param name="by"/>
        <xsl:choose>
            <xsl:when test="contains($text, $replace)">
                <xsl:value-of select="substring-before($text,$replace)"/>
                <xsl:value-of select="$by"/>
                <xsl:call-template name="string-replace-all">
                    <xsl:with-param name="text" select="substring-after($text,$replace)"/>
                    <xsl:with-param name="replace" select="$replace"/>
                    <xsl:with-param name="by" select="$by"/>
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$text"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>

    <xsl:template name="replace-quotes">
        <xsl:param name="text"/>
        <xsl:param name="searchString">"</xsl:param>
        <xsl:param name="replaceString">'</xsl:param>
        <xsl:choose>
            <xsl:when test="contains($text,$searchString)">
                <xsl:value-of select="substring-before($text,$searchString)"/>
                <xsl:value-of select="$replaceString"/>
                <!--  recursive call -->
                <xsl:call-template name="replace-quotes">
                    <xsl:with-param name="text" select="substring-after($text,$searchString)"/>
                    <xsl:with-param name="searchString" select="$searchString"/>
                    <xsl:with-param name="replaceString" select="$replaceString"/>
                </xsl:call-template>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="$text"/>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>


    <xsl:template match="/">
        <xsl:variable name="Quote">"</xsl:variable>
        <xsl:variable name="Apos">'</xsl:variable>
        
        <xsl:variable name="id">
            <xsl:call-template name="string-replace-all">
                <xsl:with-param name="text" select="TEI:TEI/TEI:teiHeader/@xml:id" />
                <xsl:with-param name="replace" select="'L1916_'" />
                <xsl:with-param name="by" select="''" />
            </xsl:call-template>
        </xsl:variable>
        
        <xsl:variable name="collection_idno">
            <xsl:if test="TEI:TEI/TEI:teiHeader/TEI:fileDesc/TEI:sourceDesc/TEI:msDesc/TEI:msIdentifier/TEI:idno !=''">
                <xsl:text>, </xsl:text><xsl:value-of select="normalize-space(TEI:TEI/TEI:teiHeader/TEI:fileDesc/TEI:sourceDesc/TEI:msDesc/TEI:msIdentifier/TEI:idno)" /> 
            </xsl:if>
        </xsl:variable>

        <xsl:text>{</xsl:text>
        <xsl:text>"api_version": "1.0",</xsl:text>
        <xsl:text>"collection": "",</xsl:text>
        <xsl:text>"collection_id": "",</xsl:text>
        <xsl:text>"copyright_statement": "",</xsl:text>
        <xsl:text>"creator": "</xsl:text> <xsl:value-of select="TEI:TEI/TEI:teiHeader/TEI:profileDesc/TEI:correspDesc/TEI:correspAction[@type='sent']/TEI:persName" /><xsl:text>",</xsl:text>
        <xsl:text>"creator_gender": "</xsl:text> <xsl:value-of select="TEI:TEI/TEI:teiHeader/TEI:profileDesc/TEI:textClass/TEI:keywords/TEI:list/TEI:item[@n='gender']" /><xsl:text>",</xsl:text>
        <xsl:text>"creator_location": "</xsl:text> <xsl:value-of select="TEI:TEI/TEI:teiHeader/TEI:profileDesc/TEI:correspDesc/TEI:correspAction[@type='sent']/TEI:placeName" /><xsl:text>",</xsl:text>
        <xsl:text>"date_created": "</xsl:text><xsl:value-of select="TEI:TEI/TEI:teiHeader/TEI:profileDesc/TEI:correspDesc/TEI:correspAction[@type='sent']/TEI:date/@when" /><xsl:text>",</xsl:text>
        <xsl:text>"description": "</xsl:text><xsl:value-of select="TEI:TEI/TEI:teiHeader/TEI:fileDesc/TEI:notesStmt/TEI:note" /><xsl:text>",</xsl:text>
        <xsl:text>"doc_collection": "</xsl:text><xsl:value-of select="normalize-space(TEI:TEI/TEI:teiHeader/TEI:fileDesc/TEI:sourceDesc/TEI:msDesc/TEI:msIdentifier/TEI:collection)" /><xsl:copy-of select="$collection_idno" />"<xsl:text>,</xsl:text>
        <xsl:text>"language": "</xsl:text><xsl:value-of select="TEI:TEI/TEI:teiHeader/TEI:profileDesc/TEI:langUsage/TEI:language" /><xsl:text>",</xsl:text>
        <xsl:text>"document_id": "</xsl:text><xsl:value-of select="$id" /><xsl:text>",</xsl:text>
        <xsl:text>"modified_timestamp": "</xsl:text><xsl:value-of select="TEI:TEI/TEI:teiHeader/TEI:revisionDesc/TEI:change[last()]/@when" /><xsl:text>",</xsl:text>
        <xsl:text>"number_pages": "</xsl:text><xsl:value-of select="count(TEI:TEI/TEI:facsimile/TEI:graphic)" /><xsl:text>",</xsl:text>

        <!-- pages -->
        <xsl:if test="count(TEI:TEI/TEI:facsimile/TEI:graphic)>0">
            <xsl:text>"pages": [</xsl:text>
        </xsl:if>
        <xsl:for-each select="TEI:TEI/TEI:facsimile/TEI:graphic">
            <xsl:text>{</xsl:text>
            <xsl:variable name="pageindex" select="position()"/>

            <xsl:text>"archive_filename": "</xsl:text><xsl:value-of select="@url" /><xsl:text>",</xsl:text>
            <xsl:text>"contributor": "",</xsl:text>
            <xsl:text>"doc_collection_identifier": "",</xsl:text>
            <xsl:text>"last_rev_timestamp": "",</xsl:text>
            <xsl:text>"original_filename": "",</xsl:text>
            <xsl:text>"page_count": "</xsl:text><xsl:value-of select="position()" /><xsl:text>",</xsl:text>
            <xsl:text>"page_id": "",</xsl:text>
            <xsl:text>"page_type": "</xsl:text><xsl:value-of select="/TEI:TEI/TEI:text/TEI:group/TEI:text[$pageindex]/@type" /><xsl:text>",</xsl:text>
            <xsl:text>"rev_id": "",</xsl:text>
            <xsl:text>"rev_name": "",</xsl:text>
            <!-- transcription -->   
            
            <xsl:text>"transcription": "</xsl:text>
            <xsl:apply-templates mode="copy"
                select="/TEI:TEI/TEI:text/TEI:group/TEI:text[$pageindex]/*" />
            <xsl:text>",</xsl:text>
            
            <xsl:text>"transcription_status": "2"</xsl:text>
            <!-- transcription -->

            <xsl:text>}</xsl:text>
            <xsl:if test="position()!=last()">
                <xsl:text>,</xsl:text>
            </xsl:if>
        </xsl:for-each>
        <xsl:if test="count(TEI:TEI/TEI:facsimile/TEI:graphic)>0">
            <xsl:text>],</xsl:text>
        </xsl:if>
        <!-- pages -->

        <xsl:text>"recipient": "</xsl:text><xsl:value-of select="TEI:TEI/TEI:teiHeader/TEI:profileDesc/TEI:correspDesc/TEI:correspAction[@type='received']/TEI:persName" /><xsl:text>",</xsl:text>
        <xsl:text>"recipient_location": "</xsl:text><xsl:value-of select="TEI:TEI/TEI:teiHeader/TEI:profileDesc/TEI:correspDesc/TEI:correspAction[@type='received']/TEI:placeName" /><xsl:text>",</xsl:text>
        <xsl:text>"request_time": "",</xsl:text>
        <xsl:text>"source": "</xsl:text><xsl:value-of select="TEI:TEI/TEI:teiHeader/TEI:fileDesc/TEI:sourceDesc/TEI:msDesc/TEI:msIdentifier/TEI:repository"/><xsl:text>",</xsl:text>
        <xsl:text>"status": "1",</xsl:text>
        <xsl:text>"terms_of_use": "",</xsl:text>
        <xsl:text>"time_zone": "Europe/Dublin",</xsl:text>
        <xsl:text>"title": "</xsl:text><xsl:value-of select="TEI:TEI/TEI:teiHeader/TEI:fileDesc/TEI:titleStmt/TEI:title" /><xsl:text>",</xsl:text>

        <!-- topics -->
        <xsl:if test="count(TEI:TEI/TEI:teiHeader/TEI:profileDesc/TEI:textClass/TEI:keywords/TEI:list/TEI:item[@n='tag'])>0">
            <xsl:text>"topics": [</xsl:text>
        </xsl:if>
        <xsl:for-each select="TEI:TEI/TEI:teiHeader/TEI:profileDesc/TEI:textClass/TEI:keywords/TEI:list/TEI:item[@n='tag']">
            <xsl:text>{</xsl:text>
                <xsl:text>"topic_id":"",</xsl:text>
                <xsl:text>"topic_name": "</xsl:text><xsl:value-of select="text()"/><xsl:text>"</xsl:text>
            <xsl:text>}</xsl:text>
            <xsl:if test="position()!=last()">
                <xsl:text>,</xsl:text>
            </xsl:if>
        </xsl:for-each>
        <xsl:if test="count(TEI:TEI/TEI:teiHeader/TEI:profileDesc/TEI:textClass/TEI:keywords/TEI:list/TEI:item[@n='tag'])>0">
            <xsl:text>],</xsl:text>
        </xsl:if>
        <!-- topics -->
        <xsl:text>"transcription_status": "2",</xsl:text>
        <xsl:text>"type": "xml_tei_letter19xx",</xsl:text>
        <xsl:text>"user_id": "",</xsl:text>
        <xsl:text>"year_of_death_of_author": ""</xsl:text>
        <xsl:text>}</xsl:text>

    </xsl:template>



</xsl:stylesheet>
