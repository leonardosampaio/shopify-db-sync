<?php
//https://github.com/Shopify/polaris-react/blob/main/examples/cdn-styles/index.html
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>DB Sync Settings</title>
    <link
      rel="stylesheet"
      href="https://unpkg.com/@shopify/polaris@5.0.0/dist/styles.css"
    />
  </head>

  <body>
    <div
      style="
        --top-bar-background: #00848e;
        --top-bar-background-lighter: #1d9ba4;
        --top-bar-color: #f9fafb;
        --p-frame-offset: 0px;
      "
    >
      <div class="Polaris-Page">
	<form id="form">
        <div
          class="Polaris-Page-Header Polaris-Page-Header--separator Polaris-Page-Header--hasNavigation Polaris-Page-Header--hasActionMenu"
        >
          <div class="Polaris-Page-Header__Navigation">
            <div class="Polaris-Page-Header__BreadcrumbWrapper">
              <nav role="navigation">
                <a
                  class="Polaris-Breadcrumbs__Breadcrumb"
                  href="#"
		  onclick="window.history.back();"
                  data-polaris-unstyled="true"
                  ><span class="Polaris-Breadcrumbs__ContentWrapper"
                    ><span class="Polaris-Breadcrumbs__Icon"
                      ><span class="Polaris-Icon"
                        ><svg
                          viewBox="0 0 20 20"
                          class="Polaris-Icon__Svg"
                          focusable="false"
                          aria-hidden="true"
                        >
                          <path
                            d="M12 16a.997.997 0 0 1-.707-.293l-5-5a.999.999 0 0 1 0-1.414l5-5a.999.999 0 1 1 1.414 1.414L8.414 10l4.293 4.293A.999.999 0 0 1 12 16"
                            fill-rule="evenodd"
                          ></path></svg></span></span
                    ><span class="Polaris-Breadcrumbs__Content"
                      >Apps</span
                    ></span
                  ></a
                >
              </nav>
            </div>
          </div>
          <div class="Polaris-Page-Header__MainContent">
            <div class="Polaris-Page-Header__TitleActionMenuWrapper">
              <div class="Polaris-Page-Header__Title">
                <div>
                  <h1
                    class="Polaris-DisplayText Polaris-DisplayText--sizeLarge"
                  >
                    DB Sync
                  </h1>
                </div>
              </div>

        <div class="Polaris-Page__Content">
          <div class="Polaris-Layout">
            <div class="Polaris-Layout__AnnotatedSection">
              <div class="Polaris-Layout__AnnotationWrapper">
                <div class="Polaris-Layout__Annotation">
                  <div class="Polaris-TextContainer">
                    <h2 class="Polaris-Heading">Settings</h2>
                    <p>Input API key</p>
                  </div>
                </div>
                <div class="Polaris-Layout__AnnotationContent">
                  <div class="Polaris-Card">
                    <div class="Polaris-Card__Section">
                      <div class="Polaris-FormLayout">
                        <div role="group" class="">
                          <div class="Polaris-FormLayout__Items">
                            <div class="Polaris-FormLayout__Item">
                              <div class="">
                                <div class="Polaris-Labelled__LabelWrapper">
                                  <div class="Polaris-Label">
                                    <label
                                      id="TextField1Label"
                                      for="TextField1"
                                      class="Polaris-Label__Text"
                                      >AuthKey</label
                                    >
                                  </div>
                                </div>
                                <div class="Polaris-TextField">
                                  <input
                                    id="key"
                                    value="<?php echo $templateParams['key'];?>"
                                    placeholder="Format XYZ"
                                    class="Polaris-TextField__Input"
                                    aria-labelledby="TextField1Label"
                                    aria-invalid="false"
				    name="key"
				    <?php if ($templateParams['disabled']) { echo "disabled=disabled"; } ?>
                                  />
                                  <div
                                    class="Polaris-TextField__Backdrop"
                                  ></div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="Polaris-FormLayout__Item">
                          <button
                            type="button"
                            class="Polaris-Button Polaris-Button--<?php echo $templateParams['disabled'] ? "disabled" : "primary"; ?>"
		            onclick="document.getElementById('form').submit()"
                            <?php if ($templateParams['disabled']) { echo "disabled=disabled"; } ?>
                          >
                            <span class="Polaris-Button__Content"
                              ><span>Submit</span></span
                            >
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
	<input type=hidden name=shop value="<?php echo $templateParams['shop']; ?>">
	<input type=hidden name=accessToken value="<?php echo $templateParams['accessToken']; ?>">
	</form>
    </div>
  </body>
</html>
