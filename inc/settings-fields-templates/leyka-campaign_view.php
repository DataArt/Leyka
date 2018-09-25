<?php if( !defined('WPINC') ) die;

/** Custom field group for the Campaign decoration Step of the Init wizard. */

/** @var $this Leyka_Custom_Setting_Block A block for which the template is used. */
?>

<?php

$campaign_id = get_transient( 'leyka_init_campaign_id' );
$campaign = new Leyka_Campaign($campaign_id);
$campaign_thumbnail_id = get_post_thumbnail_id($campaign_id);
$cur_template = $campaign->template;
if(!$cur_template || $cur_template == 'default') {
    $cur_template = 'revo';
    update_post_meta($campaign_id, 'campaign_template', $cur_template);
    $campaign->template = $cur_template;
}

$templates = leyka()->get_templates();

wp_enqueue_media();

?>

<input type="hidden" value="<?php echo $campaign_id?>" id="leyka-decor-campaign-id" />

<div id="<?php echo $this->id;?>" class="settings-block custom-block <?php echo $this->field_type;?>">
    
    <div class="campaign-decor-wrap">
        
        <div class="decor-form">
            
            <div id="campaign_photo" class="settings-block option-block upload-photo-field">
                
                <div id="leyka_campaign_photo-wrapper">
                    <label for="leyka_campaign_photo-field">
                        <span class="field-component title">
                            Фото миниатюры кампании
                            <span class="field-q">
                                <img src="<?php echo LEYKA_PLUGIN_BASE_URL?>img/icon-q.svg" />
                                <span class="field-q-tooltip">Установите главную фотографию вашей кампании</span>
                            </span>
                        </span>
                        <span class="field-component field">
                            <input type="file" value="" />
                            <input type="button" class="button upload-photo" id="campaign_photo-upload-button" value="Выбрать фотографию" />
                        </span>
                    </label>
                    <?php wp_nonce_field( 'set-campaign-photo', 'set-campaign-photo-nonce' )?>
                    <input type="hidden" id="leyka-campaign_thumbnail" name="campaign_thumbnail" value="<?php echo $campaign_thumbnail_id?>" />
                </div>
                <div class="field-errors"></div>
                
            </div>

            <div id="campaign_template" class="settings-block option-block upload-photo-field">
                
                <div id="leyka_campaign_template-wrapper">
                    <label for="leyka_campaign_template-field">
                        <span class="field-component title">
                            Выбрать шаблон формы
                            <span class="field-q">
                                <img src="<?php echo LEYKA_PLUGIN_BASE_URL?>img/icon-q.svg" />
                                <span class="field-q-tooltip">Выберите шаблон формы кампании</span>
                            </span>
                        </span>
                        <span class="field-component field">
                            
                            <select id="leyka_campaign_template-field" name="campaign_template">
                                <?php 
                                if($templates) {
                                    foreach($templates as $template) {?>
                                    <option value="<?php echo esc_attr($template['id']);?>" <?php selected($cur_template, $template['id'])?>>
                                        <?php esc_html_e(esc_attr($template['name']), 'leyka')?>
                                    </option>
                                <?php }
                                }?>
                
                            </select>
                        </span>
                    </label>
                    <?php wp_nonce_field( 'set-campaign-template', 'set-campaign-template-nonce' )?>
                    <input type="hidden" id="leyka-campaign_template" name="campaign_template" value="<?php echo $cur_template?>" />
                </div>
                <div class="field-errors"></div>
                
            </div>
            
            <div id="campaign-decoration-loading">
                 <div class="loader-wrap">
                    <span class="leyka-loader md"></span>
                 </div>
            </div>

        </div>
        
        <div class="decor-preview">
            
            <div class="title">Как будет выглядеть на сайте</div>
            
            <div class="preview-frame <?php echo $cur_template?>" id="leyka-preview-frame">
                <?php
                    $embed_code = Leyka_Campaign_Management::get_card_embed_code($campaign_id, false, 343, 700);
                    $embed_code = str_replace('embed_object=campaign_card', 'embed_object=campaign_card_templated', $embed_code);
                    echo $embed_code;
                ?>
            </div>
            
        </div>
        
    </div>
            
    
</div>