<?php
include_once("php_includes/check_login_status.php");

// Check if user is already logged in
if ($user_ok != true) {
    header("Location: https://airlonger.com/sign-in"); // Redirect away from this page
    exit();
}


if (isset($_GET['code'])) {


    $categoryCode  = preg_replace('#[^0-9a-z]#i', '', $_GET['code']);



}else{
  header("location: https://airlonger.com/welcome");
  exit();
}



//GET ALL PRODUCT Segments Associated With this Store

$storeID = $_SESSION['storeId'];


$storecategories = '';
$getcategory = mysqli_query($db_conx, "
    SELECT smcm.category_id, c.cat_name, c.cat_code 
    FROM store_market_category_mapping AS smcm 
    INNER JOIN category AS c ON smcm.category_id = c.cat_id 
    WHERE smcm.store_id = '$storeID' 
    LIMIT 100
");

while ($sps = mysqli_fetch_array($getcategory, MYSQLI_ASSOC)) {
  
    $category_id = $sps['category_id'];
    $cat_name = $sps['cat_name'];
    $cat_code = $sps['cat_code'];

    
    $storecategories .= '<li class="nk-menu-item">
                                                        <a href="store-ledger/'.$cat_code.'" class="nk-menu-link ">
                                                           '.ucfirst($cat_name).'
                                                        </a>
                                                    </li>';
}


//Get details from Product Log
$allproductLogs = '';
$allproductLogVariants = '';
$productmodal = '';
$addProductBtn = '';
$productPrices = '';


// First query to get the category name
$categoryQuery = "SELECT cat_name, cat_id FROM category WHERE cat_code = ?";
$categoryStmt = $db_conx->prepare($categoryQuery);
$categoryStmt->bind_param("s", $categoryCode);  // Bind categoryCode safely
$categoryStmt->execute();
$categoryResult = $categoryStmt->get_result();

if ($categoryResult->num_rows > 0) {
    $categoryRow = $categoryResult->fetch_assoc();
    $categoryName = $categoryRow['cat_name'];
    $categoryId = $categoryRow['cat_id'];
} else {
    // If no category found, handle this case
    $categoryName = '<em class="icon ni ni-alert-c"></em> Unidentified category';
    // You can choose to return or proceed based on how you want to handle this
    return;
}


// Fetch product logs associated with the store's selected category
// Second query to fetch the product logs associated with the store and selected category
$productLogQuery = "SELECT p.log_id, p.log_name, p.log_code 
                    FROM store_category_product_mapping AS scpm 
                    INNER JOIN product_log AS p ON scpm.product_id = p.log_id 
                    WHERE scpm.category_id = (SELECT cat_id FROM category WHERE cat_code = ?) 
                    AND scpm.store_id = ? 
                    LIMIT 100";
$productLogStmt = $db_conx->prepare($productLogQuery);
$productLogStmt->bind_param("si", $categoryCode, $storeID);  // Bind categoryCode and storeID safely
$productLogStmt->execute();
$productLogResult = $productLogStmt->get_result();

if ($productLogResult->num_rows < 1) {
    // If no products found, display message and link to the next page
    $allproductLogs = '<div class="alert alert-warning text-center"><h6 class="title">No products found in the ' . $categoryName . ' category </h6><a href="store-category/'.$categoryCode.'" class="btn btn-outline-primary btn-lg">Add Product</a></div>';
} else {
    while ($log = $productLogResult->fetch_assoc()) {
        $log_id = $log['log_id'];
        $log_code = $log['log_code'];
        $log_name = $log['log_name'];

        $addProductBtn = '<a href="store-category/'.$categoryCode.'" class="btn btn-outline-primary btn-lg">Add Product</a>';

        // Prepare query to fetch the product image
        $productImageQuery = "SELECT meda_name FROM product_media_data WHERE product_log_id = ? ORDER BY meda_priority DESC LIMIT 1";
        $productImageStmt = $db_conx->prepare($productImageQuery);
        $productImageStmt->bind_param("i", $log_id);  // Bind log_id

        if (!$productImageStmt->execute()) {
            die("Query execution failed: " . $productImageStmt->error);
        }

        $productImageResult = $productImageStmt->get_result();
        if (!$productImageResult) {
            die("Result retrieval failed: " . $productImageStmt->error);
        }

        // Check if any image is returned, otherwise show a placeholder
        if ($productImageResult->num_rows > 0) {
            $getProductImage = $productImageResult->fetch_assoc();
            $productPhoto = '<img src="uploads/thumb_' . $getProductImage['meda_name'] . '" alt="' . $log_name . '" class="card-img-top" data-bs-toggle="modal" data-bs-target="#product' . $log_id . 'Modal">';
        } else {
            $productPhoto = '<img src="content_delivery/images/image-placeholder.jpg" alt="' . $log_name . '" class="card-img-top" data-bs-toggle="modal" data-bs-target="#product' . $log_id . 'Modal">';
        }



        // Get all attributions associated with this product
        $productAttribution = '';
        $attributionQuery = "SELECT a.attribution_id, a.attribution_name, a.attribution_code, c.cat_name 
                             FROM product_attribution AS a 
                             INNER JOIN category AS c ON a.category_id = c.cat_id  
                             WHERE a.log_id = ? LIMIT 10";
        $attributionStmt = $db_conx->prepare($attributionQuery);
        $attributionStmt->bind_param("i", $log_id);  // Bind log_id
        $attributionStmt->execute();
        $attributionResult = $attributionStmt->get_result();

        // Check if any results were returned
        if ($attributionResult->num_rows > 0) {
            while ($att = $attributionResult->fetch_assoc()) {
                $attribution_id = $att['attribution_id'];
                $attribution_code = $att['attribution_code'];
                $attribution_name = $att['attribution_name'];

                // check if this user has access to the store
                if ($store_access_ok == true){
                    $productAttribution .= '<a href="widget/' . $attribution_code . '" class="btn">
                        <i class="fa-solid fa-check" title="Review"></i>
                        ' . ucwords($attribution_name) . '
                      </a>';

                }else{
                    $productAttribution = '<a href="#" class="btn">
                        <i class="fa-solid fa-star" title="Review"></i>
                        Widget
                      </a>';
                }
            }
        } else {
            //get the details to move to attribution page
            $getlinkups = mysqli_query($db_conx, "SELECT ms.ms_id, ms.ms_name, id.inds_id, id.inds_name FROM market_segment_category_mapping AS mscm INNER JOIN market_segment AS ms ON mscm.ms_id = ms.ms_id INNER JOIN industries AS id ON ms.industry_id = id.inds_id WHERE mscm.cat_id = '$categoryId' LIMIT 1");
            $gettheLinks = mysqli_fetch_assoc($getlinkups);


            $url = 'category-attribution/'.urlencode(preg_replace('/[^A-Za-z0-9]/', '', strtolower($categoryName))).'/'.$categoryId.'/'.urlencode(preg_replace('/[^A-Za-z0-9]/', '', strtolower($gettheLinks['ms_name']))).'/'.$gettheLinks['ms_id'].'/'.urlencode(preg_replace('/[^A-Za-z0-9]/', '', strtolower($gettheLinks['inds_name']))).'/'.$gettheLinks['inds_id'].'#'.preg_replace('/[^A-Za-z0-9]/', '', strtolower($log_name));


            // If no results found, show a message
            

            if ($store_access_ok == true){
                $productAttribution .= '<a href="'.$url.'" class="btn">
                        <i class="fa-solid fa-plus" title="Review"></i>
                        Generate
                      </a>';

            }else{
                $productAttribution = 'No product to pick from this item. <em class="icon ni ni-cart"></em>';
            }


        }

        // Close the statement
        $attributionStmt->close();


        // Get store product variants
        $allproductLogVariants = '';
        $storeProductsQuery = "SELECT spv.spv_id, spv.spv_name, st.product_code, spv.spv_code, st.stock_quantity, spv.image_id 
                               FROM store_product_variants AS spv 
                               INNER JOIN stockings AS st ON spv.spv_id = st.spv_id 
                               WHERE spv.log_id = ? AND spv.store_id = ? 
                               ORDER BY spv.spv_priority DESC LIMIT 20";
        $storeProductsStmt = $db_conx->prepare($storeProductsQuery);
        $storeProductsStmt->bind_param("ii", $log_id, $storeID);  // Bind log_id and storeID
        $storeProductsStmt->execute();
        $storeProductsResult = $storeProductsStmt->get_result();

        if ($storeProductsResult->num_rows < 1) {
            $allproductLogVariants = '<div class="alert alert-warning text-center">
                    No product variant added yet 
                  </div> <div class="scrolling-menu">'.$productAttribution.'</div>';
        } else {
            while ($stp = $storeProductsResult->fetch_assoc()) {
                $product_id = $stp['spv_id'];
                $product_name = $stp['spv_name'];
                $product_code = $stp['product_code'];
                $spv_code = $stp['spv_code'];
                $stock_quantity = $stp['stock_quantity'];

                //get 


                //Get product prices
                $getPrices = mysqli_query($db_conx, "SELECT prpr_data FROM product_prices WHERE spv_id = '$product_id' ORDER BY prpr_priority DESC LIMIT 3");
                while ($thePrices = mysqli_fetch_assoc($getPrices)) {

                    $priceData = json_decode($thePrices['prpr_data'], true);

                    $priceUom = $priceData['uom'];
                    $priceAmount = $priceData['amount'];
                    $priceCurrency = $priceData['currency'];
                    
                    $productPrices .= '<div class="price-stock">
                            <span class="price">N'.number_format($priceAmount, 2).' <span class="uom">/ '.$priceUom.'</span></span>
                            <span class="stock-info badge rounded-pill bg-outline-dark">' . number_format($stock_quantity) . '</span>
                          </div>';
                }

                // check if this user has access to the store
                if ($store_access_ok == true){

                    $allproductLogVariants .= '<div class="variant-item">
                        <a href="stocking/' . $spv_code . '"><img src="content_delivery/images/image-placeholder.jpg" alt="' . $product_name . ' - ' . $spv_code . '">
                        <div class="details"></a>
                          <span style="font-size: 1rem;">' . $product_name . '</span>
                          '.$productPrices.'
                        </div>
                      </div>';

                }else{

                    $allproductLogVariants .= '<div class="variant-item">
                        <a href="order/' . $spv_code . '"><img src="content_delivery/images/image-placeholder.jpg" alt="' . $product_name . ' - ' . $spv_code . '">
                        <div class="details"></a>
                          <span>' . $product_name . '</span>
                          <div class="price-stock">
                            <span class="price">$10 <span class="uom">/ LB</span></span>
                            <span class="stock-info">' . number_format($stock_quantity) . '/span>
                          </div>
                        </div>
                      </div>';
                }
            }
        }
       

        


        // Prepare the product log and modal content
        $allproductLogs .= '<div class="col">
            <div class="card h-100">
              <div class="square-box">
                ' . $productPhoto . '
              </div>
              <div class="card-body p-1 lh-1 text-center">
                ' . ucwords($log_name) . '
              </div>
              <div class="card-footer p-0">
                <div class="scrolling-menu">
                  ' . $productAttribution. '
                  <button class="btn">
                    <i class="fa-solid fa-bell" title="Reminder"></i>
                    0
                  </button>
                </div>
              </div>
            </div>
          </div>';

        $productmodal .= '<div class="modal fade" id="product' . $log_id . 'Modal" tabindex="-1" aria-labelledby="product' . $log_id . 'ModalLabel" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="product' . $log_id . 'ModalLabel">' . ucwords($log_name) . '</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  ' . $allproductLogVariants . '
                </div>
              </div>
            </div>
          </div>';


    }
}




$allmyCategories = '';
//Get All categories associated with this store

//select all market segment selected by the store
$storemarkets = mysqli_query($db_conx, "SELECT m.ms_id, m.ms_name, m.ms_icon, m.ms_code FROM market_segment as m INNER JOIN store_market_segment_mapping as s ON m.ms_id = s.ms_id WHERE s.store_id = '$storeID' ORDER BY m.ms_priority DESC LIMIT 50");

while ($stmks = mysqli_fetch_assoc($storemarkets)) {
    
    $ms_id = $stmks['ms_id'];
    $ms_name = $stmks['ms_name'];
    $ms_code = $stmks['ms_code'];

    $thenamechild = '';

    $myCategories  = mysqli_query($db_conx, "SELECT c.cat_id, c.cat_name FROM store_market_category_mapping AS smcm INNER JOIN category AS c ON smcm.category_id = c.cat_id WHERE smcm.market_id = '$ms_id' AND smcm.store_id = '$storeID' ORDER BY c.cat_priority DESC LIMIT 10");
    while ($row = mysqli_fetch_array($myCategories, MYSQLI_ASSOC)) {
        
        $cat_id = $row['cat_id'];
        $cat_name = $row['cat_name'];

        $thenamechild .= '<option value="'.$cat_id.'">'.ucwords($cat_name).'</option>';

    }

    $allmyCategories .= '<div class="nk-file-item mb-4">
                                                        <div class="nk-file-info">
                                                            <select class="badge rounded-pill text-black fw-medium px-2 pt-1 pb-1" style="font-size:1em">
                                                                <option>'.ucwords($ms_name).'</option>
                                                                '.$thenamechild.'
                                                            </select>
                                                        </div>
                                                    </div>';
}



$pageTitle = $_SESSION['storeName'].' Products';
$pageDescription = 'Products and SKUs for '.$_SESSION['storeName'];
$pageConical = 'https://airlonger.com/store-ledger/'.$categoryCode;
$pageImage = 'homephoto.jpg';
$pageKewords = 'Products, ';
$pageCrawlers = 'noindex, nofollow'; // index, follow


// Call the function on page load
setPreviousPageCookie();

?>
<!DOCTYPE html>
<html lang="en" class="js">
<head>
    <?php include_once("meta_tags.php");?>

    <style type="text/css">
        
         /*SCROLL*/
         #scrollmenu, .scrllm {
         display: flex;
         overflow-x: auto;
         white-space: nowrap;
         -webkit-overflow-scrolling: touch; /* Optional for smoother scrolling on iOS */
         width: 100%; /* Set a width for the container */
         }
         .scrolldiv {
         flex: 0 0 auto;
         width: 300px; /* Adjust the width of each column as needed */
         margin-right: 10px; /* Adjust the margin between columns as needed */
         }
         .horizontal-scroll {
         overflow-x: auto;
         white-space: nowrap;
         padding: 10px;

         }
         .horizontal-scroll::-webkit-scrollbar {
         display: none;
         }
         /* Hide the scrollbar for other browsers */
         .horizontal-scroll {
         scrollbar-width: none;
         }
      </style>
      <script type="text/javascript">
         document.addEventListener('DOMContentLoaded', function () {
         const scrollmenu = document.getElementById('scrollmenu');
         
         scrollmenu.addEventListener('wheel', (e) => {
         if (e.deltaMode == 0 && e.deltaX != 0) {
         e.preventDefault();
         scrollmenu.scrollLeft += e.deltaX;
         }
         });
         });
         
      </script>
          <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    .square-box {
      position: relative;
      width: 100%;
      padding-top: 100%; /* Makes the box square */
      overflow: hidden;
    }

    .square-box img {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover; /* Ensures the image covers the box without distortion */
      cursor: pointer;
    }

    .scrolling-menu {
      display: flex;
      overflow-x: auto;
      padding: 5px;
      gap: 10px;
    }

    .scrolling-menu .btn {
      font-size: 0.65rem;
      display: flex;
      align-items: center;
      gap: 5px;
      padding: 3px 8px;
      border-radius: 20px;
      background-color: #f8f9fa;
      border: 1px solid #e0e0e0;
      white-space: nowrap; /* Prevent breaking */
    }

    
    .variant-item {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 15px;
    }

    .variant-item img {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 8px; /* Added for a crispy feel */
    }

    .variant-item .details {
      display: flex;
      flex-direction: column;
    }

    .variant-item .details .price {
      font-size: 1rem;
      font-weight: bold;
    }

    .variant-item .details .price span.uom {
      font-size: 0.85rem;
      color: gray; /* Smaller text with gray color for UOM */
    }



    .stock-info {
      font-size: 0.9rem;
      color: gray;
    }

    .stock-info .price-stock {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
  </style>
</head>

<body class="nk-body npc-apps apps-only has-apps-sidebar npc-apps-files">
    <div class="nk-app-root">

         <?php include_once("hd_left_end_sidebar.php");?>

        <!-- main @s -->
        <div class="nk-main ">
            <!-- wrap @s -->

            <div class="nk-wrap ">
                <!-- main header @s -->

                <?php include_once("hd_topHeader.php");?>

                <!-- main header @e -->
                <?php include_once("hd_topSideBarLinks.php");?>
                <!-- content @s -->

                <div class="nk-content p-0">
                    <div class="nk-content-inner">
                        <div class="nk-content-body">
                            <div class="nk-fmg">

                                <!-- //PAGE SIDE BAR -->
                                <div class="nk-fmg-aside" data-content="files-aside" data-toggle-overlay="true" data-toggle-body="true" data-toggle-screen="lg" data-simplebar>

                                    <div class="nk-chat-list">
                                            <h6 class="title overline-title-alt mt-2">Categories</h6>
                                            <div class="dropdown-body">
                                                <ul class="nk-menu"><?php echo $storecategories; ?></ul>
                                            </div>
                                    </div>

                                   
                                </div>
                                <!-- //PAGE SIDE BAR -->

                                <div class="nk-fmg-body">
                                    
                                    <?php include_once("hd_pageHeader.php"); ?>

                                    <div class="nk-fmg-body-content">

                                        <!--ONPAGE HEADER-->
                                        <div class="nk-block-head nk-block-head-sm">
                                            <div class="nk-block-between position-relative">
                                                
                                                <div class="nk-block-head-content">
                                                   
                                                    <div class="nk-block-head-sub">
                                                      
                                                    </div>
                                                     <a class="back-to" href="redirect">
                                                        <em class="icon ni ni-arrow-left"></em><span>Back</span></a>
                                                    </a>
                                                    
                                                    <h4 class="nk-block-title fw-normal"><?php echo $categoryName; ?></h4>
                                                </div>

                                                <div class="nk-block-head-content">
                                                    <ul class="nk-block-tools g-1">
                                                        <li class="d-lg-none">
                                                            <a href="#" class="btn btn-trigger btn-icon search-toggle toggle-search" data-target="search"><em class="icon ni ni-search"></em></a>
                                                        </li>
                                                        <li class="d-lg-none">
                                                            <div class="dropdown">
                                                                <a href="store-category/<?php echo $categoryCode; ?>" class="btn btn-trigger btn-icon"><em class="icon ni ni-plus-c"></em></a>
                                                            </div>
                                                        </li>
                                                        
                                                        <li class="d-lg-none me-n1"><a href="#" class="btn btn-trigger btn-icon toggle" data-target="files-aside"><em class="icon ni ni-menu-alt-r"></em></a></li>
                                                    </ul>
                                                </div>
                                                <div class="search-wrap px-2 d-lg-none" data-search="search">
                                                    <div class="search-content">
                                                        <a href="#" class="search-back btn btn-icon toggle-search" data-target="search"><em class="icon ni ni-arrow-left"></em></a>
                                                        <input type="text" class="form-control border-transparent form-focus-none" placeholder="Search by user or message">
                                                        <button class="search-submit btn btn-icon"><em class="icon ni ni-search"></em></button>
                                                    </div>
                                                </div><!-- .search-wrap -->
                                                <div class="nk-block-head-content d-none d-lg-block lead-text">
                                                    <?php echo $addProductBtn; ?>
                                                    
                                                </div>
                                            </div>
                                        </div>

                                        


                                        <!--//ONPAGE HEADER-->

                                        
                                        <!--PAGE CONTENT INSIDE THIS DIV-->
                                        <div class="nk-fmg-listing nk-block row">

                                            <div class="col-12">
                                                
                                                <div class="nk-files nk-files-view-grid">
                                                    <div class="nk-files-head" id="scrollableDiv" style="overflow: scroll;">

                                                        <?php echo $allmyCategories; ?>
                                                    </div>
                                                </div>

                                            </div>

                                            <!--SELECTED ATTRIBUTIONS-->
                                            

                                            <div class="container my-5">
                                                <div class="row row-cols-2 row-cols-sm-2 row-cols-md-5 g-4">
                                                    <?php echo $allproductLogs; ?>
                                                </div>
                                              </div>

                                            <!--//SELECTED ATTRIBUTIONS-->



                                        </div>
                                        <!--//PAGE CONTENT INSIDE THIS DIV-->


                                    </div><!-- .nk-fmg-body-content -->
                                </div><!-- .nk-fmg-body -->
                            </div><!-- .nk-fmg -->
                        </div>
                    </div>
                </div>
                <!-- content @e -->
            </div>
            <!-- wrap @e -->
        </div>
        <!-- main @e -->
    </div>
    <!-- app-root @e -->




<?php echo $productmodal; ?>


<script type="text/javascript">

    function saveMyStock(inventoryID, proledgeID, proSegID, storeID, isChecked) {
    var statusElement = document.getElementById('status' + inventoryID);
    var myListElement = document.getElementById('myList');

    // Create an AJAX object
    var xhttp = new XMLHttpRequest();

    // Define the callback function for when the request completes
    xhttp.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
            // Update the status element with the response from the PHP script
            statusElement.innerHTML = this.responseText;

            // Update the #myList value based on the checkbox state
            if (isChecked) {
                // Checkbox is checked, increment #myList by 1
                myListElement.innerHTML = parseInt(myListElement.innerHTML) + 1;
            } else {
                // Checkbox is unchecked, decrement #myList by 1
                myListElement.innerHTML = parseInt(myListElement.innerHTML) - 1;
            }
        }
    };

    // Open a POST request to the PHP script
    xhttp.open("POST", "requests/add_to_stock.php", true);

    // Set the Content-Type header for sending JSON data
    xhttp.setRequestHeader("Content-Type", "application/json;charset=UTF-8");

    // Prepare the data to be sent as a JSON object
    var data = {
        inventoryID: inventoryID,
        proledgeID: proledgeID,
        proSegID: proSegID,
        storeID: storeID,
        isChecked: isChecked
    };

    // Send the request with the JSON data
    xhttp.send(JSON.stringify(data));
}

 

    <!-- JavaScript -->
    <script src="./assets/js/bundle.js?ver=3.2.1"></script>
    <script src="./assets/js/scripts.js?ver=3.2.1"></script>
    <script src="./assets/js/apps/file-manager.js?ver=3.2.1"></script>
     <script>
        document.querySelector('.redirect').addEventListener('click', function () {
          history.back();
        });

    </script>


</script>
</body>

</html>