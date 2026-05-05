{$meta_title=$btr->sviat_log_viewer__title scope=global}

<div class="main_header">
    <div class="main_header__item">
        <div class="main_header__inner">
            <div class="box_heading heading_page">
                {$btr->sviat_log_viewer__title|escape}
                — {$current_source_label|escape}
                {if $total_entries_count} ({$total_entries_count}){/if}
            </div>
            <div class="box_btn_heading">
                {if $selected_date}
                    <a class="btn btn_small btn-info"
                       href="{url controller='Sviat.LogViewer.LogViewerAdmin@downloadFile' source=$current_source date=$selected_date}">
                        {include file='svg_icon.tpl' svgId='download'}
                        <span>{$btr->sviat_log_viewer__download|escape}</span>
                    </a>
                    {if $can_delete_selected}
                        <button type="button"
                                class="btn btn_small btn-danger fn_log_viewer_delete_open"
                                data-date="{$selected_date|escape}"
                                data-toggle="modal"
                                data-target="#sviat_log_viewer_delete_modal">
                            {include file='svg_icon.tpl' svgId='trash'}
                            <span>{$btr->sviat_log_viewer__delete_file|escape}</span>
                        </button>
                    {/if}
                {/if}
            </div>
        </div>
    </div>
</div>

{if $flash}
    {if $flash.type == 'success'}
        {assign var="alert_modifier" value="alert--success"}
        {assign var="alert_title" value=$btr->sviat_log_viewer__alert_title_success}
    {else}
        {assign var="alert_modifier" value="alert--error"}
        {assign var="alert_title" value=$btr->sviat_log_viewer__alert_title_error}
    {/if}
    <div class="alert alert--icon {$alert_modifier} mt-1">
        <div class="alert__content">
            <div class="alert__title">{$alert_title|escape}</div>
            <p>
                {if $flash.context && $flash.context.date}
                    {$flash.text|escape} {$flash.context.date|escape}
                {else}
                    {$flash.text|escape}
                {/if}
            </p>
        </div>
    </div>
{/if}

{* Source tabs (URL navigation styled as OkayCMS tabs) *}
<div class="heading_tabs sviat_log_viewer_tabs">
    <div class="tab_navigation tab_navigation--round">
        {foreach $source_labels as $src_key => $src_label}
            <a href="{url controller='Sviat.LogViewer.LogViewerAdmin' source=$src_key date=null level=null q=null page=null}"
               class="heading_box tab_navigation_link fn_sviat_log_viewer_tab{if $src_key == $current_source} selected{/if}">
                {if $src_key == 'app'}
                    {include file='svg_icon.tpl' svgId='warn_icon'}
                {else}
                    {include file='svg_icon.tpl' svgId='date'}
                {/if}
                {$src_label|escape}
            </a>
        {/foreach}
    </div>
</div>

<div class="boxed">
    {* Filters *}
    <div class="row mb-1">
        <div class="col-lg-3 col-md-3 col-sm-12">
            <div class="heading_label">{$btr->sviat_log_viewer__col_time|escape}</div>
            {if $log_files}
                <select class="selectpicker form-control" onchange="location = this.value;">
                    <option value="{url date=null page=null}">{$btr->sviat_log_viewer__all_dates|escape}</option>
                    {foreach $log_files as $file}
                        <option value="{url date=$file.date page=null}" {if $selected_date == $file.date}selected{/if}>
                            {$file.date|escape} — {($file.size/1024)|string_format:"%.1f"} KB
                        </option>
                    {/foreach}
                </select>
            {else}
                <select class="selectpicker form-control" disabled>
                    <option>{$btr->sviat_log_viewer__no_files|escape}</option>
                </select>
            {/if}
        </div>

        <div class="col-lg-3 col-md-3 col-sm-12">
            <div class="heading_label">{$btr->sviat_log_viewer__col_level|escape}</div>
            <select class="selectpicker form-control" onchange="location = this.value;">
                <option value="{url level=null page=null}" {if !$filter_level}selected{/if}>
                    {$btr->sviat_log_viewer__all_levels|escape}
                </option>
                {foreach $level_labels as $lvl => $lvl_label}
                    <option value="{url level=$lvl page=null}" {if $filter_level == $lvl}selected{/if}>
                        {$lvl_label|escape}
                    </option>
                {/foreach}
            </select>
        </div>

        <div class="col-lg-2 col-md-2 col-sm-12">
            <div class="heading_label">{$btr->general_show_by|escape}</div>
            <select class="selectpicker form-control" onchange="location = this.value;">
                {foreach $limits as $lim}
                    <option value="{url limit=$lim page=null}" {if $current_limit == $lim}selected{/if}>
                        {$lim}
                    </option>
                {/foreach}
            </select>
        </div>

        <div class="col-lg-4 col-md-4 col-sm-12">
            <div class="heading_label">&nbsp;</div>
            <form method="get" class="sviat_log_viewer_search">
                <input type="hidden" name="controller" value="Sviat.LogViewer.LogViewerAdmin">
                <input type="hidden" name="source" value="{$current_source|escape}">
                {if $selected_date}<input type="hidden" name="date" value="{$selected_date|escape}">{/if}
                {if $filter_level}<input type="hidden" name="level" value="{$filter_level|escape}">{/if}
                <input type="hidden" name="limit" value="{$current_limit|escape}">
                <div class="input-group input-group--search{if $keyword || $filter_level || $selected_date} sviat_log_viewer_search--has-reset{/if}">
                    <input type="text"
                           name="q"
                           class="form-control"
                           value="{$keyword|escape}"
                           maxlength="200"
                           placeholder="{$btr->sviat_log_viewer__search_placeholder|escape}">
                    <span class="input-group-btn">
                        {if $keyword || $filter_level || $selected_date}
                            <a class="btn sviat_log_viewer_reset_btn"
                               href="{url controller='Sviat.LogViewer.LogViewerAdmin' source=$current_source date=null level=null q=null page=null}">
                                <i class="fa fa-times"></i>
                            </a>
                        {/if}
                        <button type="submit" class="btn"><i class="fa fa-search"></i></button>
                    </span>
                </div>
            </form>
        </div>
    </div>

    {if $too_large}
        <div class="alert alert--icon alert--warning">
            <div class="alert__content">
                <div class="alert__title">{$btr->sviat_log_viewer__alert_title_warning|escape}</div>
                <p>
                    {$btr->sviat_log_viewer__too_large|escape}
                    ({($too_large_size/1024/1024)|string_format:"%.1f"} MB,
                    {$btr->sviat_log_viewer__limit_is|escape}
                    {($max_parse_bytes/1024/1024)|string_format:"%.0f"} MB).
                </p>
                {if $selected_date}
                    <a class="btn btn_small btn-info mt-1"
                       href="{url controller='Sviat.LogViewer.LogViewerAdmin@downloadFile' source=$current_source date=$selected_date}">
                        {include file='svg_icon.tpl' svgId='download'}
                        <span>{$btr->sviat_log_viewer__download|escape}</span>
                    </a>
                {/if}
            </div>
        </div>
    {elseif !$log_entries}
        <div class="alert alert--icon">
            <div class="alert__content">
                <div class="alert__title">{$btr->sviat_log_viewer__alert_title_info|escape}</div>
                <p>
                    {if !$log_files}
                        {$btr->sviat_log_viewer__no_files|escape}
                    {else}
                        {$btr->sviat_log_viewer__no_entries|escape}
                    {/if}
                </p>
            </div>
        </div>
    {else}
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="okay_list products_list sviat_log_viewer_list">
                    <div class="okay_list_head">
                        <div class="okay_list_heading sviat_log_viewer_col_time">
                            {$btr->sviat_log_viewer__col_time|escape}
                        </div>
                        <div class="okay_list_heading sviat_log_viewer_col_level">
                            {$btr->sviat_log_viewer__col_level|escape}
                        </div>
                        <div class="okay_list_heading sviat_log_viewer_col_message">
                            {$btr->sviat_log_viewer__col_message|escape}
                        </div>
                    </div>
                    <div class="okay_list_body">
                        {foreach $log_entries as $entry}
                            <div class="okay_list_body_item">
                                <div class="okay_list_row">
                                    <div class="okay_list_boding sviat_log_viewer_col_time">
                                        <div class="text_grey font_13">{$entry.timestamp|escape}</div>
                                    </div>
                                    <div class="okay_list_boding sviat_log_viewer_col_level">
                                        <div class="wrap_tags">
                                            {if $entry.level == 'CRITICAL'}
                                                <span class="tag tag-danger upper blink">{$entry.level|escape}</span>
                                            {elseif $entry.level == 'ERROR'}
                                                <span class="tag tag-danger upper">{$entry.level|escape}</span>
                                            {elseif $entry.level == 'WARNING'}
                                                <span class="tag tag-warning upper">{$entry.level|escape}</span>
                                            {elseif $entry.level == 'NOTICE' || $entry.level == 'INFO'}
                                                <span class="tag tag-info upper">{$entry.level|escape}</span>
                                            {elseif $entry.level == 'DEBUG'}
                                                <span class="tag tag-secondary upper">{$entry.level|escape}</span>
                                            {else}
                                                <span class="tag upper">{$entry.level|escape}</span>
                                            {/if}
                                        </div>
                                    </div>
                                    <div class="okay_list_boding sviat_log_viewer_col_message">
                                        <div class="text_dark sviat_log_viewer_msg_text">
                                            {$entry.message|escape}
                                        </div>
                                        {if $entry.is_long_message || $entry.has_trace}
                                            <div class="sviat_log_viewer_more"
                                                 id="sviat_log_viewer_more_{$entry.id|escape}">
                                                <a href="javascript:;"
                                                   class="fn_sviat_log_viewer_toggle sviat_log_viewer_toggle_link"
                                                   data-target="#sviat_log_viewer_more_{$entry.id|escape}"
                                                   data-text-show="{$btr->sviat_log_viewer__show_details|escape}"
                                                   data-text-hide="{$btr->sviat_log_viewer__hide_details|escape}">
                                                    {include file='svg_icon.tpl' svgId='eye'}<span class="fn_sviat_log_viewer_toggle_label">{$btr->sviat_log_viewer__show_details|escape}</span>
                                                </a>
                                                <div class="sviat_log_viewer_panel">
                                                    <button type="button"
                                                            class="btn btn_mini btn-light fn_sviat_log_viewer_copy"
                                                            data-target="#sviat_log_viewer_pre_{$entry.id|escape}"
                                                            data-label-default="{$btr->sviat_log_viewer__copy|escape}"
                                                            data-label-copied="{$btr->sviat_log_viewer__copied|escape}">
                                                        {include file='svg_icon.tpl' svgId='icon_copy'}
                                                        <span class="fn_sviat_log_viewer_copy_label">
                                                            {$btr->sviat_log_viewer__copy|escape}
                                                        </span>
                                                    </button>
                                                    <div class="sviat_log_viewer_panel_content"
                                                         id="sviat_log_viewer_pre_{$entry.id|escape}">{if $entry.trace}{$entry.trace|escape}{else}{$entry.full_message|escape}{/if}</div>
                                                </div>
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        {/foreach}
                    </div>
                </div>
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12 txt_center">
                {include file='pagination.tpl'}
            </div>
        </div>
    {/if}
</div>

{* Delete confirmation modal *}
<div id="sviat_log_viewer_delete_modal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="card-header">
                <div class="heading_modal">{$btr->sviat_log_viewer__delete_file|escape}</div>
            </div>
            <div class="modal-body">
                <form method="post"
                      action="{url controller='Sviat.LogViewer.LogViewerAdmin@deleteFile'}">
                    <input type="hidden" name="session_id" value="{$smarty.session.id}">
                    <input type="hidden" name="source" value="{$current_source|escape}">
                    <input type="hidden" name="date" id="sviat_log_viewer_delete_date" value="">

                    <p>
                        {$btr->sviat_log_viewer__confirm_delete|escape}
                        <strong id="sviat_log_viewer_delete_label"></strong>
                    </p>
                    <p class="text_grey">{$btr->sviat_log_viewer__confirm_delete_warn|escape}</p>

                    <div class="row">
                        <div class="col-lg-12 col-md-12 mt-1">
                            <button type="submit" class="btn btn_small btn-danger">
                                {include file='svg_icon.tpl' svgId='trash'}
                                <span>{$btr->sviat_log_viewer__delete_file|escape}</span>
                            </button>
                            <button type="button" class="btn btn_small btn-light" data-dismiss="modal">
                                <span>{$btr->general_cancel|escape}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

