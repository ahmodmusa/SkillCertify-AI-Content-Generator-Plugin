<?php
/**
 * @var array $stats
 * @var array $activities
 */
defined( 'ABSPATH' ) || exit;
?>

<div class="wrap">
    <h1>🤖 AI Content Dashboard</h1>
    <p>Overview of AI-generated content status</p>

    <div class="sc-ai-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
        
        <!-- Total Questions -->
        <div class="sc-ai-card" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">Total Questions</h3>
            <div style="font-size: 48px; font-weight: bold; color: #2271b1;"><?php echo esc_html( $stats['total'] ); ?></div>
        </div>

        <!-- Generated -->
        <div class="sc-ai-card" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">Generated</h3>
            <div style="font-size: 48px; font-weight: bold; color: #00a32a;"><?php echo esc_html( $stats['final'] ); ?></div>
            <?php if ( $stats['last_final'] ) : ?>
            <div style="margin-top: 10px; font-size: 12px; color: #646970;">Last: <?php echo esc_html( $stats['last_final'] ); ?></div>
            <?php endif; ?>
        </div>

        <!-- Pending -->
        <div class="sc-ai-card" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px;">Pending</h3>
            <div style="font-size: 48px; font-weight: bold; color: #646970;"><?php echo esc_html( $stats['pending'] ); ?></div>
        </div>
        
    </div>
    
    <!-- Progress Bar -->
    <div style="margin-top: 30px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px;">
        <h3 style="margin: 0 0 15px 0;">Content Generation Progress</h3>
        <?php 
        $progress = $stats['total'] > 0 ? ( $stats['final'] / $stats['total'] * 100 ) : 0;
        ?>
        <div style="background: #e5e5e5; height: 20px; border-radius: 10px; overflow: hidden;">
            <div style="background: linear-gradient(90deg, #2271b1 0%, #00a32a 100%); height: 100%; width: <?php echo esc_attr( $progress ); ?>%; transition: width 0.3s;"></div>
        </div>
        <div style="margin-top: 10px; font-size: 14px; color: #646970;">
            <strong><?php echo number_format( $progress, 1 ); ?>%</strong> complete (<?php echo esc_html( $stats['final'] ); ?> of <?php echo esc_html( $stats['total'] ); ?> questions)
        </div>
    </div>
    
    <!-- Activity Timeline -->
    <div style="margin-top: 20px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px;">
        <h3 style="margin: 0 0 15px 0;">Recent Activity (Last 20)</h3>
        <?php if ( empty( $activities ) ) : ?>
        <p style="color: #646970; font-style: italic;">No recent activity recorded.</p>
        <?php else : ?>
        <div style="max-height: 400px; overflow-y: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead style="position: sticky; top: 0; background: #f9f9f9; z-index: 1;">
                    <tr>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Type</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Message</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #e5e5e5;">Generated Time</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $activities as $activity ) : ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 8px;">
                            <span style="color: #00a32a; font-weight: bold;">
                                <?php echo esc_html( ucfirst( $activity['type'] ) ); ?>
                            </span>
                        </td>
                        <td style="padding: 8px;"><?php echo esc_html( $activity['message'] ); ?></td>
                        <td style="padding: 8px;">
                            <?php if ( ! empty( $activity['question_title'] ) ) : ?>
                                <a href="<?php echo esc_url( get_permalink( $activity['question_id'] ) ); ?>" style="color: #2271b1; text-decoration: none;" target="_blank">
                                    <?php echo esc_html( $activity['question_title'] ); ?>
                                </a>
                            <?php else : ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 8px; color: #646970;"><?php echo esc_html( $activity['time'] ); ?></td>
                        <td style="padding: 8px;">
                            <?php if ( ! empty( $activity['question_id'] ) ) : ?>
                                <?php 
                                $has_content = get_post_meta( $activity['question_id'], '_scp_ai_description', true );
                                ?>
                                <?php if ( ! $has_content ) : ?>
                                    <button class="button button-small" style="font-size: 11px; padding: 4px 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; border-radius: 12px; font-weight: 500; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);" onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.4)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(102, 126, 234, 0.3)';" onclick="scAiGenerate(<?php echo esc_attr( $activity['question_id'] ); ?>); setTimeout(() => location.reload(), 2500);">
                                        ✨ Generate
                                    </button>
                                <?php else : ?>
                                    <span style="color: #00a32a; font-size: 12px; font-weight: 500; background: #e7f3ed; padding: 4px 10px; border-radius: 12px;">✓ Done</span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Question Status Table -->
    <div style="margin-top: 20px; background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 8px;">
        <h3 style="margin: 0 0 15px 0;">Question Status</h3>

        <!-- Filters -->
        <div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <button class="sc-ai-filter button button-primary" data-filter="all">All</button>
            <button class="sc-ai-filter button" data-filter="pending">Pending</button>
            <button class="sc-ai-filter button" data-filter="complete">Complete</button>
            <span style="margin-left: auto; color: #646970; font-size: 13px;">Select up to 20 items for batch generation</span>
            <button class="button button-primary" id="sc-ai-batch-generate" style="display: none;">Generate Selected (0)</button>
        </div>

        <!-- Table -->
        <div class="sc-ai-table-container" style="overflow-x: auto;">
            <table class="sc-ai-status-table wp-list-table widefat fixed striped" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 40px;"><input type="checkbox" id="sc-ai-select-all"></th>
                        <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e5e5; min-width: 500px;">Question Title</th>
                        <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 80px;">Status</th>
                        <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 110px;">Generated Time</th>
                        <th style="padding: 8px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="sc-ai-table-body">
                    <tr>
                        <td colspan="5" style="padding: 20px; text-align: center;">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="sc-ai-pagination tablenav bottom" style="margin-top: 15px;">
            <div class="tablenav-pages" id="sc-ai-page-controls"></div>
        </div>
    </div>

</div>
